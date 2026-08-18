<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\CacheControl;

/**
 * The hint for a field, resolved from its own `@cacheControl` annotation combined with the one on
 * its declared return type.
 *
 * This is the part of the computation that depends only on the schema, so it is memoized per
 * `FieldDefinition` by {@see CacheHintResolver}. Applying `defaultMaxAge` is deliberately *not*
 * done here — that step needs to know whether the field is a root field, which is only known at
 * execution time.
 */
final class FieldCacheHint
{
    private ?int $maxAge;
    private ?string $scope;
    private bool $inheritMaxAge;
    private bool $composite;

    public function __construct(?int $maxAge, ?string $scope, bool $inheritMaxAge, bool $composite)
    {
        $this->maxAge = $maxAge;
        $this->scope = $scope;
        $this->inheritMaxAge = $inheritMaxAge;
        $this->composite = $composite;
    }

    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function shouldInheritMaxAge(): bool
    {
        return $this->inheritMaxAge;
    }

    /**
     * Whether the field's named return type is an object, interface or union.
     */
    public function returnsCompositeType(): bool
    {
        return $this->composite;
    }
}
