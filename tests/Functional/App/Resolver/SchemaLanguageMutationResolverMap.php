<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\Resolver;

use Redeye\GraphQLBundle\Resolver\ResolverMap;

class SchemaLanguageMutationResolverMap extends ResolverMap
{
    protected function map(): array
    {
        return [
            'Mutation' => [
                'resurrectZigZag' => [Characters::class, 'resurrectZigZag'],
            ],
        ];
    }
}
