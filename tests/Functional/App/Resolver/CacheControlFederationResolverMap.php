<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\Resolver;

use Redeye\GraphQLBundle\Resolver\ResolverMap;
use function array_values;

class CacheControlFederationResolverMap extends ResolverMap
{
    protected function map(): array
    {
        return [
            'Query' => [
                'posts' => fn () => array_values(CacheControlReferenceResolver::POSTS),
                'uncached' => fn () => 'anything',
                'content' => fn () => null,
                'timestamped' => fn () => null,
            ],
            'Content' => [
                self::RESOLVE_TYPE => fn ($value) => isset($value['title']) ? 'Post' : 'Author',
            ],
            'Timestamped' => [
                self::RESOLVE_TYPE => fn () => 'Post',
            ],
        ];
    }
}
