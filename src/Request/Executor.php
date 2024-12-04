<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Request;

use ArrayObject;
use Closure;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Redeye\GraphQLBundle\Event\Events;
use Redeye\GraphQLBundle\Event\ExecutorArgumentsEvent;
use Redeye\GraphQLBundle\Event\ExecutorContextEvent;
use Redeye\GraphQLBundle\Event\ExecutorResultEvent;
use Redeye\GraphQLBundle\Executor\ExecutorInterface;
use Redeye\GraphQLBundle\Executor\TracingReferenceExecutor;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function array_keys;
use function is_callable;
use function sprintf;

class Executor
{
    public const PROMISE_ADAPTER_SERVICE_ID = 'redeye_graphql.promise_adapter';

    private array $schemas = [];
    private EventDispatcherInterface $dispatcher;
    private PromiseAdapter $promiseAdapter;
    private ExecutorInterface $executor;
    private bool $useExperimentalExecutor; // TODO: remove in 1.0

    /**
     * @var callable|null
     */
    private $defaultFieldResolver;

    public function __construct(
        ExecutorInterface $executor,
        PromiseAdapter $promiseAdapter,
        EventDispatcherInterface $dispatcher,
        ?callable $defaultFieldResolver = null,
        bool $useExperimental = false // TODO: remove in 1.0
    ) {
        $this->executor = $executor;
        $this->promiseAdapter = $promiseAdapter;
        $this->dispatcher = $dispatcher;
        $this->defaultFieldResolver = $defaultFieldResolver;
        $this->useExperimentalExecutor = $useExperimental; // TODO: remove in 1.0
    }

    public function setExecutor(ExecutorInterface $executor): self
    {
        $this->executor = $executor;

        return $this;
    }

    public function addSchemaBuilder(string $name, Closure $builder): self
    {
        $this->schemas[$name] = $builder;

        return $this;
    }

    public function addSchema(string $name, Schema $schema): self
    {
        $this->schemas[$name] = $schema;

        return $this;
    }

    public function getSchema(string $name = null): Schema
    {
        if (empty($this->schemas)) {
            throw new RuntimeException('At least one schema should be declare.');
        }

        if (null === $name) {
            $name = isset($this->schemas['default']) ? 'default' : array_key_first($this->schemas);
        }

        if (!isset($this->schemas[$name])) {
            throw new NotFoundHttpException(sprintf('Could not found "%s" schema.', $name));
        }

        $schema = $this->schemas[$name];
        if (is_callable($schema)) {
            $schema = $schema();
            $this->addSchema((string) $name, $schema);
        }

        return $schema;
    }

    public function getSchemasNames(): array
    {
        return array_keys($this->schemas);
    }

    public function setMaxQueryDepth(int $maxQueryDepth): void
    {
        /** @var QueryDepth $queryDepth */
        $queryDepth = DocumentValidator::getRule(QueryDepth::class);
        $queryDepth->setMaxQueryDepth($maxQueryDepth);
    }

    public function setMaxQueryComplexity(int $maxQueryComplexity): void
    {
        /** @var QueryComplexity $queryComplexity */
        $queryComplexity = DocumentValidator::getRule(QueryComplexity::class);
        $queryComplexity->setMaxQueryComplexity($maxQueryComplexity);
    }

    public function enableIntrospectionQuery(): void
    {
        DocumentValidator::addRule(new DisableIntrospection(DisableIntrospection::DISABLED));
    }

    public function disableIntrospectionQuery(): void
    {
        DocumentValidator::addRule(new DisableIntrospection());
    }

    /**
     * @param array|ArrayObject|object|null $rootValue
     */
    public function execute(?string $schemaName, array $request, $rootValue = null): ExecutionResult
    {
        \GraphQL\Executor\Executor::setImplementationFactory([TracingReferenceExecutor::class, 'create']);

        $schema = $this->getSchema($schemaName);
        /** @var string $schemaName */
        $schemaName = array_search($schema, $this->schemas);

        $executorArgumentsEvent = $this->preExecute(
            $schemaName,
            $schema,
            $request[ParserInterface::PARAM_QUERY] ?? null,
            new ArrayObject(),
            $rootValue,
            $request[ParserInterface::PARAM_VARIABLES],
            $request[ParserInterface::PARAM_OPERATION_NAME] ?? null
        );

        $executorArgumentsEvent->getSchema()->processExtensions();

        $result = $this->executor->execute(
            $this->promiseAdapter,
            $executorArgumentsEvent->getSchema(),
            $executorArgumentsEvent->getRequestString(),
            $executorArgumentsEvent->getRootValue(),
            $executorArgumentsEvent->getContextValue(),
            $executorArgumentsEvent->getVariableValue(),
            $executorArgumentsEvent->getOperationName(),
            $this->defaultFieldResolver
        );

        $result = $this->postExecute($result, $executorArgumentsEvent);

        return $result;
    }

    /**
     * @param mixed $rootValue
     */
    private function preExecute(
        string $schemaName,
        Schema $schema,
        string $requestString,
        ArrayObject $contextValue,
        $rootValue = null,
        ?array $variableValue = null,
        ?string $operationName = null
    ): ExecutorArgumentsEvent {
        // @phpstan-ignore-next-line (only for Symfony 4.4)
        $this->dispatcher->dispatch(new ExecutorContextEvent($contextValue), Events::EXECUTOR_CONTEXT);

        /** @var ExecutorArgumentsEvent $object */
        // @phpstan-ignore-next-line (only for Symfony 4.4)
        $object = $this->dispatcher->dispatch(
            // @phpstan-ignore-next-line
            ExecutorArgumentsEvent::create($schemaName, $schema, $requestString, $contextValue, $rootValue, $variableValue, $operationName),
            Events::PRE_EXECUTOR
        );

        return $object;
    }

    private function postExecute(ExecutionResult $result, ExecutorArgumentsEvent $executorArguments): ExecutionResult
    {
        // @phpstan-ignore-next-line (only for Symfony 4.4)
        return $this->dispatcher->dispatch(
            new ExecutorResultEvent($result, $executorArguments),
            Events::POST_EXECUTOR
        )->getResult();
    }
}
