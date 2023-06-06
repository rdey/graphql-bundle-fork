<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Resolver;

use Redeye\GraphQLBundle\Resolver\QueryResolver;

class ResolverResolverTest extends AbstractProxyResolverTest
{
    protected function createResolver(): QueryResolver
    {
        return new QueryResolver();
    }
}
