<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\Resolver;

use Redeye\GraphQLBundle\Error\UserError;
use Redeye\GraphQLBundle\Resolver\ResolverMap;

class CacheControlQueryResolverMap extends ResolverMap
{
    private const POSTS = [
        ['id' => '1', 'title' => 'First'],
        ['id' => '2', 'title' => 'Second'],
    ];

    protected function map(): array
    {
        return [
            'Query' => [
                'posts' => fn () => self::POSTS,
                'featured' => fn () => self::POSTS[0],
                'me' => fn () => ['id' => '42', 'email' => 'ryan@example.com'],
                'shortLived' => fn () => 'ephemeral',
                'uncached' => fn () => 'anything',
                'boom' => function (): void {
                    throw new UserError('Kaboom.');
                },
            ],
        ];
    }
}
