<?php
declare(strict_types=1);

namespace Redeye\GraphQLBundle\Executor;

use GraphQL\Executor\ExecutorImplementation;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Executor\ReferenceExecutor;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use Redeye\GraphQLBundle\CacheControl\CacheControlRecorder;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanContext;
use Sentry\State\Scope;
use ArrayObject;
use function is_array;

class TracingReferenceExecutor extends ReferenceExecutor
{
    private array $sentryContext = [];

    public static function create(PromiseAdapter $promiseAdapter, Schema $schema, DocumentNode $documentNode, $rootValue, $contextValue, $variableValues, ?string $operationName, callable $fieldResolver): ExecutorImplementation
    {
        $referenceExecutor = parent::create($promiseAdapter, $schema, $documentNode, $rootValue, $contextValue, $variableValues, $operationName, $fieldResolver);

        if ($referenceExecutor instanceof TracingReferenceExecutor) {
            $referenceExecutor->sentryContext = [
                'query' => Printer::doPrint($documentNode),
                'operationName' => $operationName,
            ];
            $referenceExecutor->configureContext();

            return $referenceExecutor;
        }

        return $referenceExecutor;
    }

    protected function resolveFieldValueOrError(FieldDefinition $fieldDef, FieldNode $fieldNode, callable $resolveFn, $rootValue, ResolveInfo $info)
    {
        // Deliberately first: this is the equivalent of Apollo's willResolveField, and it must not
        // depend on the Sentry block below having worked.
        $this->recordCacheControl($info);

        $argumentNodes = $fieldNode->arguments ?? [];
        foreach ($argumentNodes as $argumentNode) {
            if (!$argumentNode->value instanceof VariableNode) {
                continue;
            }

            $argumentName = $argumentNode->name->value;
            $variableName = $argumentNode->value->name->value;

            if (!($arg = $this->findFieldArgumentNamed($fieldDef, $argumentName))) {
                continue;
            }

            if ($this->shouldTraceFieldArgument($arg)) {
                $this->sentryContext['arguments']['$'.$argumentName] = $this->exeContext->variableValues[$variableName];
            }
        }

        $this->configureContext();

        $span = null;
        $parent = null;

        if (class_exists('Sentry\SentrySdk') && null !== $parent = SentrySdk::getCurrentHub()->getSpan()) {
            $context = new SpanContext();
            $context->setOp('graphql.resolve_field');
            $context->setDescription(implode('.', $info->path));
            $span = $parent->startChild($context);

            // Set the current span to the span we just started
            SentrySdk::getCurrentHub()->setSpan($span);
        }

        try {
            return parent::resolveFieldValueOrError($fieldDef, $fieldNode, $resolveFn, $rootValue, $info);
        } finally {
            // We only have a span if we started a span earlier
            if (null !== $span) {
                $span->finish();

                // Restore the current span back to the parent span
                SentrySdk::getCurrentHub()->setSpan($parent);
            }
        }
    }

    /**
     * Feeds the field into the recorder for the operation being executed, if there is one.
     *
     * The recorder travels in the GraphQL context value rather than being injected, because this
     * class is built by a static factory inside graphql-php and so has no access to the container.
     * A schema executed outside this bundle's request executor simply has no recorder and records
     * nothing.
     */
    private function recordCacheControl(ResolveInfo $info): void
    {
        $context = $this->exeContext->contextValue;

        if (!$context instanceof ArrayObject && !is_array($context)) {
            return;
        }

        $recorder = $context[CacheControlRecorder::CONTEXT_KEY] ?? null;

        if ($recorder instanceof CacheControlRecorder) {
            $recorder->recordField($info);
        }
    }

    private function findFieldArgumentNamed(FieldDefinition $def, string $name): ?FieldArgument
    {
        foreach ($def->args as $arg) {
            if ($arg->name === $name) {
                return $arg;
            }
        }

        return null;
    }

    private function shouldTraceFieldArgument(FieldArgument $arg): bool
    {
        // @todo Do something smarter here
        return false;
    }

    private function configureContext()
    {
        if (class_exists('Sentry\State\Scope') ) {
            \Sentry\configureScope(function (Scope $scope): void {
                $scope->setContext('graphql_query', $this->sentryContext);
            });
        }
    }
}
