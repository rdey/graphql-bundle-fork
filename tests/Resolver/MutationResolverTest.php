<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Resolver;

use Redeye\GraphQLBundle\Resolver\MutationResolver;

class MutationResolverTest extends AbstractProxyResolverTest
{
    protected function createResolver(): MutationResolver
    {
        return new MutationResolver();
    }
}
