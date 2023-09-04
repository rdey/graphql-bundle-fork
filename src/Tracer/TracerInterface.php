<?php
declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tracer;

interface TracerInterface
{
    public function beforeResolver(callable $resolver, array $args);

    public function afterResolver(callable $resolver, array $args);
}
