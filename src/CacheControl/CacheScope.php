<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\CacheControl;

/**
 * The values of the `CacheControlScope` enum used by the `@cacheControl` directive.
 *
 * A plain constant holder rather than a native enum. That was originally forced by the PHP 8.0
 * floor; the floor is 8.2 now, so this could be converted to a native enum.
 */
final class CacheScope
{
    public const PUBLIC = 'PUBLIC';
    public const PRIVATE = 'PRIVATE';

    public const ALL = [self::PUBLIC, self::PRIVATE];

    private function __construct()
    {
    }

    /**
     * PRIVATE is more restrictive than PUBLIC, which is in turn more restrictive than "unset".
     */
    public static function restrict(?string $current, ?string $candidate): ?string
    {
        if (null === $candidate) {
            return $current;
        }

        if (self::PRIVATE === $current || self::PRIVATE === $candidate) {
            return self::PRIVATE;
        }

        return $candidate;
    }

    /**
     * The `Cache-Control` token for a scope. An unset scope is PUBLIC, as in Apollo Server.
     */
    public static function toHeaderToken(?string $scope): string
    {
        return self::PRIVATE === $scope ? 'private' : 'public';
    }
}
