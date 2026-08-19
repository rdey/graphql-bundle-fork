<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\CacheControl;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use function count;

/**
 * Collects cache hints for a single GraphQL operation.
 *
 * One recorder exists per executed operation and travels in the GraphQL context value, which is
 * where the executor and any resolver can reach it.
 */
final class CacheControlRecorder
{
    /**
     * The key this recorder is stored under in the GraphQL context value.
     */
    public const CONTEXT_KEY = 'redeye_graphql_cache_control';

    /**
     * Marks a field that supplies its own hints while resolving, so the static rules below must not
     * apply the root/composite default to it. Used for the federation `_entities` field, whose hint
     * comes from the concrete types of the representations it is asked for.
     */
    public const SELF_HINTED_CONFIG_KEY = 'cacheControlSelfHinted';

    private CacheHintResolver $hintResolver;
    private CachePolicy $policy;
    private int $defaultMaxAge;

    /**
     * Once the policy cannot get any more restrictive there is no point looking at further fields.
     * In practice one unannotated root field trips this immediately, so uncacheable operations —
     * the common case — cost a single boolean check per resolved field.
     */
    private bool $sealed = false;

    public function __construct(CacheHintResolver $hintResolver, int $defaultMaxAge)
    {
        $this->hintResolver = $hintResolver;
        $this->defaultMaxAge = $defaultMaxAge;
        $this->policy = new CachePolicy();
    }

    public function recordField(ResolveInfo $info): void
    {
        if ($this->sealed) {
            return;
        }

        $fieldDefinition = $info->fieldDefinition;

        if (!empty($fieldDefinition->config[self::SELF_HINTED_CONFIG_KEY])) {
            return;
        }

        $hint = $this->hintResolver->forField($fieldDefinition);
        $maxAge = $hint->getMaxAge();

        // Root fields, and fields returning a composite type, are uncacheable unless they say
        // otherwise. Everything else — notably non-root scalars — stays undecided and therefore
        // inherits whatever its ancestors decided.
        if (null === $maxAge && (($hint->returnsCompositeType() && !$hint->shouldInheritMaxAge()) || $this->isRootField($info))) {
            $maxAge = $this->defaultMaxAge;
        }

        $this->restrict($maxAge, $hint->getScope());
    }

    /**
     * Applies the hint of a concrete type, for fields that resolve their type at runtime.
     */
    public function recordType(Type $type): void
    {
        if ($this->sealed) {
            return;
        }

        $hint = $this->hintResolver->forType($type);

        $this->restrict($hint->getMaxAge() ?? $this->defaultMaxAge, $hint->getScope());
    }

    public function getPolicy(): CachePolicy
    {
        return $this->policy;
    }

    private function restrict(?int $maxAge, ?string $scope): void
    {
        $this->policy->restrict($maxAge, $scope);

        if ($this->policy->isFullyRestricted()) {
            $this->sealed = true;
        }
    }

    private function isRootField(ResolveInfo $info): bool
    {
        return 1 === count($info->path);
    }
}
