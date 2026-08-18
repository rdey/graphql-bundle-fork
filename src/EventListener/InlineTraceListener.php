<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\EventListener;

use Redeye\GraphQLBundle\Event\ExecutorArgumentsEvent;
use Redeye\GraphQLBundle\Event\ExecutorResultEvent;
use Redeye\GraphQLBundle\Federation\Tracing\TraceErrorFilter;
use Redeye\GraphQLBundle\Federation\Tracing\TraceTreeBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Emits an Apollo Federation inline trace (`extensions.ftv1`) when the gateway asks for one.
 *
 * Port of Apollo Server's `ApolloServerPluginInlineTrace`: `onPreExecutor` stands in for
 * `requestDidStart`, and `onPostExecutor` for `didEncounterErrors` + `willSendResponse`. The
 * per-field data in between is collected by
 * {@see \Redeye\GraphQLBundle\Executor\TracingReferenceExecutor}.
 *
 * @see docs/federation/inline-trace.md
 */
final class InlineTraceListener
{
    public const HEADER = 'apollo-federation-include-trace';
    public const HEADER_VALUE = 'ftv1';
    public const EXTENSION_KEY = 'ftv1';

    /**
     * Where the in-progress trace lives for the duration of one execution.
     *
     * It has to be the execution's context rather than a property of this service: batched
     * requests reuse the service but get a fresh context per query, and the executor -- which is
     * instantiated by graphql-php, not by the container -- can only reach it through there.
     *
     * @internal
     */
    public const CONTEXT_KEY = 'redeye_graphql.inline_trace';

    private RequestStack $requestStack;
    private TraceErrorFilter $errorFilter;

    public function __construct(RequestStack $requestStack, TraceErrorFilter $errorFilter)
    {
        $this->requestStack = $requestStack;
        $this->errorFilter = $errorFilter;
    }

    public function onPreExecutor(ExecutorArgumentsEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // No request at all under bin/console, and none in in-process test helpers.
        if (!$request instanceof Request || self::HEADER_VALUE !== $request->headers->get(self::HEADER)) {
            return;
        }

        $builder = new TraceTreeBuilder();
        $builder->startTiming();

        $event->getContextValue()[self::CONTEXT_KEY] = $builder;
    }

    public function onPostExecutor(ExecutorResultEvent $event): void
    {
        $builder = $event->getExecutorArguments()->getContextValue()[self::CONTEXT_KEY] ?? null;

        if (!$builder instanceof TraceTreeBuilder) {
            return;
        }

        $result = $event->getResult();

        // Runs before ErrorHandlerListener (priority 100 vs. 0), so these are still the raw
        // errors from execution, with their paths, nodes and extensions intact.
        foreach ($result->errors ?? [] as $error) {
            $traceError = $this->errorFilter->filter($error);

            if (null !== $traceError) {
                $builder->addError($error->path, $traceError);
            }
        }

        $builder->stopTiming();

        $result->extensions[self::EXTENSION_KEY] = base64_encode($builder->toProto()->serializeToString());
    }
}
