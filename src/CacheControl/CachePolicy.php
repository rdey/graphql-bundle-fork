<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\CacheControl;

/**
 * A cache policy that can only ever become more restrictive.
 *
 * `maxAge` is the minimum over every hint that contributed one; a hint with a null `maxAge`
 * contributes nothing. That is exactly how "inherit the parent's maxAge" works in Apollo Server:
 * a field which stays undecided simply does not lower the policy, so whatever its ancestors
 * decided still stands.
 */
final class CachePolicy
{
    private ?int $maxAge = null;
    private ?string $scope = null;

    public function restrict(?int $maxAge, ?string $scope): void
    {
        if (null !== $maxAge && (null === $this->maxAge || $maxAge < $this->maxAge)) {
            $this->maxAge = $maxAge;
        }

        $this->scope = CacheScope::restrict($this->scope, $scope);
    }

    public function restrictBy(self $other): void
    {
        $this->restrict($other->maxAge, $other->scope);
    }

    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    /**
     * A policy nobody has restricted is *not* cacheable: with no hints at all we have no reason to
     * believe the response can be stored.
     */
    public function isCacheable(): bool
    {
        return null !== $this->maxAge && $this->maxAge > 0;
    }

    /**
     * True once no further hint could possibly change the outcome.
     *
     * Requires both conditions: a policy at maxAge 0 can still degrade from PUBLIC to PRIVATE.
     */
    public function isFullyRestricted(): bool
    {
        return 0 === $this->maxAge && CacheScope::PRIVATE === $this->scope;
    }

    public function reset(): void
    {
        $this->maxAge = null;
        $this->scope = null;
    }
}
