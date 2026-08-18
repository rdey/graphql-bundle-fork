<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\Resolver;

use GraphQL\Deferred;
use Redeye\GraphQLBundle\Definition\Resolver\QueryInterface;
use RuntimeException;

final class InlineTraceResolver implements QueryInterface
{
    /**
     * @return array{name: string}
     */
    public function resolveMe(): array
    {
        return ['name' => 'Ada'];
    }

    /**
     * @return array<int, array{name: string}>
     */
    public function resolveUsers(): array
    {
        return [['name' => 'Ada'], ['name' => 'Grace']];
    }

    /**
     * Resolves through a promise, so its end time can only be recorded once the value settles.
     */
    public function resolveSlowName(array $user): Deferred
    {
        return new Deferred(static function () use ($user): string {
            usleep(3000);

            return $user['name'];
        });
    }

    public function resolveBroken(): string
    {
        throw new RuntimeException('a secret internal detail');
    }
}
