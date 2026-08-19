<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\CacheControl;

/**
 * A single `@cacheControl` annotation, as written on a field or on a type.
 *
 * Every property is nullable on purpose: Apollo's algorithm distinguishes "the annotation did not
 * mention maxAge" from "the annotation set maxAge to 0", and inheritance depends on that
 * difference. Never collapse an unset value to a default here.
 */
final class CacheHint
{
    private ?int $maxAge;
    private ?string $scope;
    private bool $inheritMaxAge;

    public function __construct(?int $maxAge = null, ?string $scope = null, bool $inheritMaxAge = false)
    {
        $this->maxAge = $maxAge;
        $this->scope = $scope;
        $this->inheritMaxAge = $inheritMaxAge;
    }

    /**
     * Builds a hint from the `cacheControl` entry of a field or type config array.
     *
     * @param array{maxAge?: int|null, scope?: string|null, inheritMaxAge?: bool|null}|null $config
     */
    public static function fromConfig(?array $config): self
    {
        if (null === $config) {
            return new self();
        }

        return new self(
            isset($config['maxAge']) ? (int) $config['maxAge'] : null,
            $config['scope'] ?? null,
            (bool) ($config['inheritMaxAge'] ?? false)
        );
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

    public function isEmpty(): bool
    {
        return null === $this->maxAge && null === $this->scope && !$this->inheritMaxAge;
    }
}
