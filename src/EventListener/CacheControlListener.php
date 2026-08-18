<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\EventListener;

use Redeye\GraphQLBundle\CacheControl\CacheControlAccumulator;
use Redeye\GraphQLBundle\CacheControl\CacheControlRecorder;
use Redeye\GraphQLBundle\Event\ExecutorContextEvent;
use Redeye\GraphQLBundle\Event\ExecutorResultEvent;

/**
 * Ties the cache-control accumulator to the lifetime of a GraphQL operation.
 *
 * The executor dispatches both of these events once per executed operation, including once per
 * query on the batch endpoint, which is what makes the batched response come out as the most
 * restrictive of its operations.
 */
final class CacheControlListener
{
    private CacheControlAccumulator $accumulator;

    public function __construct(CacheControlAccumulator $accumulator)
    {
        $this->accumulator = $accumulator;
    }

    public function onExecutorContext(ExecutorContextEvent $event): void
    {
        if (!$this->accumulator->isEnabled()) {
            return;
        }

        $context = $event->getExecutorContext();
        $context[CacheControlRecorder::CONTEXT_KEY] = $this->accumulator->startOperation();
    }

    public function onPostExecutor(ExecutorResultEvent $event): void
    {
        if (!$this->accumulator->isEnabled()) {
            return;
        }

        // Apollo makes any response carrying errors uncacheable. A request that failed to parse or
        // validate never reached the executor, so its recorder is empty and this is the only thing
        // that marks it — which is the correct outcome either way.
        $this->accumulator->finishOperation(!empty($event->getResult()->errors));
    }
}
