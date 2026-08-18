<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Executor;

use ArrayAccess;
use ArrayObject;
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
use Redeye\GraphQLBundle\EventListener\InlineTraceListener;
use Redeye\GraphQLBundle\Federation\Tracing\TraceTreeBuilder;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Tracing\SpanContext;
use Throwable;
use function is_array;

class TracingReferenceExecutor extends ReferenceExecutor
{
    private array $sentryContext = [];

    /**
     * The Apollo Federation inline trace for this execution, when the gateway asked for one.
     *
     * Resolved once here rather than looked up per field: resolveFieldValueOrError() is the
     * hottest method in the executor, and an ArrayObject lookup plus an instanceof check on
     * every field of every request is pure overhead whenever tracing is off.
     */
    private ?TraceTreeBuilder $traceBuilder = null;

    public static function create(PromiseAdapter $promiseAdapter, Schema $schema, DocumentNode $documentNode, $rootValue, $contextValue, $variableValues, ?string $operationName, callable $fieldResolver): ExecutorImplementation
    {
        $referenceExecutor = parent::create($promiseAdapter, $schema, $documentNode, $rootValue, $contextValue, $variableValues, $operationName, $fieldResolver);

        if ($referenceExecutor instanceof TracingReferenceExecutor) {
            $referenceExecutor->sentryContext = [
                'query' => Printer::doPrint($documentNode),
                'operationName' => $operationName,
            ];
            $referenceExecutor->traceBuilder = self::findTraceBuilder($contextValue);
            $referenceExecutor->configureContext();

            return $referenceExecutor;
        }

        return $referenceExecutor;
    }

    /**
     * @param mixed $contextValue
     */
    private static function findTraceBuilder($contextValue): ?TraceTreeBuilder
    {
        if (!$contextValue instanceof ArrayAccess && !is_array($contextValue)) {
            return null;
        }

        $builder = $contextValue[InlineTraceListener::CONTEXT_KEY] ?? null;

        return $builder instanceof TraceTreeBuilder ? $builder : null;
    }

    /**
     * @param mixed $rootValue
     *
     * @return mixed the resolver's return value, or the Throwable it raised
     */
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

        // Last before the call, unlike recordCacheControl() above: this one starts a clock, so the
        // Sentry setup should not be counted against the field's own duration.
        $endTrace = null !== $this->traceBuilder ? $this->traceBuilder->willResolveField($info) : null;

        try {
            $result = parent::resolveFieldValueOrError($fieldDef, $fieldNode, $resolveFn, $rootValue, $info);
        } catch (Throwable $e) {
            // The parent catches everything and returns it, so this is belt and braces.
            if (null !== $endTrace) {
                $endTrace();
            }

            throw $e;
        } finally {
            // We only have a span if we started a span earlier
            if (null !== $span) {
                $span->finish();

                // Restore the current span back to the parent span
                SentrySdk::getCurrentHub()->setSpan($parent);
            }
        }

        if (null !== $endTrace) {
            $this->recordFieldEnd($result, $endTrace);
        }

        return $result;
    }

    /**
     * Stops the clock on a field: immediately for a plain value, or when its promise settles.
     *
     * getPromise() rather than the promise adapter directly -- DataLoader hands back a
     * GraphQL\Executor\Promise\Promise, which SyncPromiseAdapter::isThenable() rejects, so
     * convertThenable() would throw on the most common async case in this bundle.
     *
     * The derived promise is deliberately discarded: this is a side branch, and the original
     * value must be returned unchanged so that completeValueCatchingError() still sees it.
     * Because resolveFieldValueOrError() runs before completeValueCatchingError(), this handler
     * is registered first and so records the end time before any child field is resolved.
     *
     * @param mixed $result
     */
    private function recordFieldEnd($result, callable $endTrace): void
    {
        $promise = $this->getPromise($result);

        if (null === $promise) {
            $endTrace();

            return;
        }

        $settle = static function () use ($endTrace): void {
            try {
                $endTrace();
            } catch (Throwable $e) {
                // Tracing must never break execution.
            }
        };

        // Both handlers return void and never rethrow: rethrowing would leave an unhandled
        // rejection on this orphan branch, which Guzzle's adapter reports from a destructor.
        $promise->then($settle, $settle);
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

    private function configureContext(): void
    {
        if (class_exists('Sentry\State\Scope')) {
            \Sentry\configureScope(function (Scope $scope): void {
                $scope->setContext('graphql_query', $this->sentryContext);
            });
        }
    }
}
