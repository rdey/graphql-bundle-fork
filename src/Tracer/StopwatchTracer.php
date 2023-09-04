<?php
declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tracer;

use Symfony\Component\Stopwatch\Stopwatch;

class StopwatchTracer implements TracerInterface
{
    private ?Stopwatch $stopwatch;
    public function __construct(?Stopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    public function beforeResolver(callable $resolver, array $args)
    {
        $name = $this->getStopwatchName($resolver);

        if ($this->stopwatch instanceof Stopwatch) {
            $this->stopwatch->start($name, 'graphql_resolve');
        }
    }

    public function afterResolver(callable $resolver, array $args)
    {
        $name = $this->getStopwatchName($name);

        if ($this->stopwatch instanceof Stopwatch) {
            $this->stopwatch->stop($name);
        }
    }

    private function getStopwatchName(callable $resolver)
    {
        $resolverObject = null;
        if (is_array($resolver) ) {
            $resolverObject = $resolver[0];
        }

        if (is_object($resolverObject)) {
            $className = $resolverObject::class;
        } elseif (is_string($resolverObject)) {
            $className = $resolverObject;
        } else {
            $className = 'N/A';
        }

        $opname = 'graphql.resolve';
        return $className . ':' . $method;
    }
}
