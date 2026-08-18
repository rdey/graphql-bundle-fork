<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\Resolver;

class CacheControlReferenceResolver
{
    public const POSTS = [
        '1' => ['id' => '1', 'title' => 'First', 'updatedAt' => '2026-01-01'],
        '2' => ['id' => '2', 'title' => 'Second', 'updatedAt' => '2026-01-02'],
    ];

    /**
     * @param array{id: string} $reference
     */
    public function resolve(array $reference): ?array
    {
        return self::POSTS[$reference['id']] ?? null;
    }
}
