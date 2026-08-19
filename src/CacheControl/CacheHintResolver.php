<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\CacheControl;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\Type;
use WeakMap;
use function is_array;

/**
 * Resolves the schema-derived part of a field's cache hint.
 *
 * This service is long-lived and shared across requests. Results are memoized in a {@see WeakMap}
 * keyed by the `FieldDefinition` object itself — never by name, because a container can hold
 * several schemas whose type and field names collide, and never by `spl_object_id`, because those
 * are recycled once an object is collected and the schema may be rebuilt in a worker runtime.
 */
final class CacheHintResolver
{
    /** @var WeakMap<FieldDefinition, FieldCacheHint> */
    private WeakMap $fieldHints;

    public function __construct()
    {
        $this->fieldHints = new WeakMap();
    }

    public function forField(FieldDefinition $fieldDefinition): FieldCacheHint
    {
        return $this->fieldHints[$fieldDefinition] ??= $this->computeFieldHint($fieldDefinition);
    }

    /**
     * The `@cacheControl` annotation written directly on a type.
     */
    public function forType(Type $type): CacheHint
    {
        $config = $type->config ?? null;

        if (!is_array($config) || !isset($config['cacheControl']) || !is_array($config['cacheControl'])) {
            return new CacheHint();
        }

        return CacheHint::fromConfig($config['cacheControl']);
    }

    private function computeFieldHint(FieldDefinition $fieldDefinition): FieldCacheHint
    {
        $namedReturnType = Type::getNamedType($fieldDefinition->getType());
        $composite = null !== $namedReturnType && Type::isCompositeType($namedReturnType);

        $maxAge = null;
        $scope = null;
        $inheritMaxAge = false;

        // The annotation on the declared return type comes first. Note this is the *declared* type,
        // so an interface- or union-typed field reads the interface/union annotation rather than
        // whichever concrete type happens to be returned at runtime.
        if ($composite) {
            /** @var Type $namedReturnType */
            $typeHint = $this->forType($namedReturnType);
            $inheritMaxAge = $typeHint->shouldInheritMaxAge();
            $maxAge = $typeHint->getMaxAge();
            $scope = $typeHint->getScope();
        }

        $fieldHint = $this->annotationOn($fieldDefinition);

        // The field's own annotation replaces only the keys it actually sets, so
        // `@cacheControl(scope: PRIVATE)` on a field keeps the maxAge declared on its return type.
        if ($fieldHint->shouldInheritMaxAge() && null === $maxAge) {
            $inheritMaxAge = true;

            if (null !== $fieldHint->getScope()) {
                $scope = $fieldHint->getScope();
            }
        } else {
            if (null !== $fieldHint->getMaxAge()) {
                $maxAge = $fieldHint->getMaxAge();
            }

            if (null !== $fieldHint->getScope()) {
                $scope = $fieldHint->getScope();
            }
        }

        return new FieldCacheHint($maxAge, $scope, $inheritMaxAge, $composite);
    }

    private function annotationOn(FieldDefinition $fieldDefinition): CacheHint
    {
        $config = $fieldDefinition->config;

        if (!isset($config['cacheControl']) || !is_array($config['cacheControl'])) {
            return new CacheHint();
        }

        return CacheHint::fromConfig($config['cacheControl']);
    }
}
