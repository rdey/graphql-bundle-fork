<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\CacheControl;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\CacheControl\CacheHintResolver;
use Redeye\GraphQLBundle\CacheControl\CacheScope;

/**
 * Covers the schema-derived half of Apollo's algorithm: combining the annotation on a field with
 * the annotation on its declared return type. Applying `defaultMaxAge` happens later and is
 * covered by {@see CacheControlRecorderTest}.
 */
class CacheHintResolverTest extends TestCase
{
    private CacheHintResolver $resolver;
    private int $typeCounter = 0;

    protected function setUp(): void
    {
        $this->resolver = new CacheHintResolver();
    }

    public function testUnannotatedScalarField(): void
    {
        $hint = $this->resolver->forField($this->fieldReturning(Type::string()));

        $this->assertNull($hint->getMaxAge());
        $this->assertNull($hint->getScope());
        $this->assertFalse($hint->shouldInheritMaxAge());
        $this->assertFalse($hint->returnsCompositeType());
    }

    public function testCompositeReturnTypeIsDetected(): void
    {
        $hint = $this->resolver->forField($this->fieldReturning($this->objectType()));

        $this->assertTrue($hint->returnsCompositeType());
    }

    public function testAnnotationOnTheReturnTypeIsUsed(): void
    {
        $type = $this->objectType(['maxAge' => 60, 'scope' => CacheScope::PRIVATE]);

        $hint = $this->resolver->forField($this->fieldReturning($type));

        $this->assertSame(60, $hint->getMaxAge());
        $this->assertSame(CacheScope::PRIVATE, $hint->getScope());
    }

    public function testAnnotationOnAScalarReturnTypeIsIrrelevant(): void
    {
        // Only composite return types carry a type-level annotation, so a scalar field is decided
        // purely by its own annotation.
        $hint = $this->resolver->forField($this->fieldReturning(Type::string()));

        $this->assertNull($hint->getMaxAge());
    }

    public function testFieldAnnotationOverridesTheTypeAnnotation(): void
    {
        $type = $this->objectType(['maxAge' => 60]);

        $hint = $this->resolver->forField($this->fieldReturning($type, ['maxAge' => 30]));

        $this->assertSame(30, $hint->getMaxAge());
    }

    public function testFieldAnnotationReplacesOnlyTheKeysItSets(): void
    {
        // A field saying only `maxAge` must keep the scope declared on its return type. Treating
        // the field annotation as a wholesale replacement silently loses PRIVATE here.
        $type = $this->objectType(['maxAge' => 60, 'scope' => CacheScope::PRIVATE]);

        $hint = $this->resolver->forField($this->fieldReturning($type, ['maxAge' => 30]));

        $this->assertSame(30, $hint->getMaxAge());
        $this->assertSame(CacheScope::PRIVATE, $hint->getScope());
    }

    public function testFieldScopeOnlyKeepsTheTypeMaxAge(): void
    {
        $type = $this->objectType(['maxAge' => 60]);

        $hint = $this->resolver->forField($this->fieldReturning($type, ['scope' => CacheScope::PRIVATE]));

        $this->assertSame(60, $hint->getMaxAge());
        $this->assertSame(CacheScope::PRIVATE, $hint->getScope());
    }

    public function testInheritMaxAgeOnTheType(): void
    {
        $type = $this->objectType(['inheritMaxAge' => true]);

        $hint = $this->resolver->forField($this->fieldReturning($type));

        $this->assertTrue($hint->shouldInheritMaxAge());
        $this->assertNull($hint->getMaxAge());
    }

    public function testInheritMaxAgeOnTheField(): void
    {
        $hint = $this->resolver->forField(
            $this->fieldReturning($this->objectType(), ['inheritMaxAge' => true])
        );

        $this->assertTrue($hint->shouldInheritMaxAge());
        $this->assertNull($hint->getMaxAge());
    }

    public function testInheritMaxAgeOnTheFieldStillAppliesItsScope(): void
    {
        $hint = $this->resolver->forField($this->fieldReturning(
            $this->objectType(),
            ['inheritMaxAge' => true, 'scope' => CacheScope::PRIVATE]
        ));

        $this->assertTrue($hint->shouldInheritMaxAge());
        $this->assertSame(CacheScope::PRIVATE, $hint->getScope());
    }

    public function testInheritMaxAgeOnTheFieldIsIgnoredWhenTheTypeSetsMaxAge(): void
    {
        // The type already decided a maxAge, so there is nothing left to inherit.
        $type = $this->objectType(['maxAge' => 60]);

        $hint = $this->resolver->forField($this->fieldReturning($type, ['inheritMaxAge' => true]));

        $this->assertFalse($hint->shouldInheritMaxAge());
        $this->assertSame(60, $hint->getMaxAge());
    }

    public function testExplicitZeroIsNotTreatedAsUnset(): void
    {
        $type = $this->objectType(['maxAge' => 60]);

        $hint = $this->resolver->forField($this->fieldReturning($type, ['maxAge' => 0]));

        $this->assertSame(0, $hint->getMaxAge());
    }

    /**
     * @dataProvider wrappedTypeProvider
     */
    public function testListAndNonNullWrappersAreUnwrapped(callable $wrap): void
    {
        $type = $this->objectType(['maxAge' => 60]);

        $hint = $this->resolver->forField($this->fieldReturning($wrap($type)));

        $this->assertSame(60, $hint->getMaxAge());
        $this->assertTrue($hint->returnsCompositeType());
    }

    public function wrappedTypeProvider(): iterable
    {
        yield 'non-null' => [fn (Type $t) => Type::nonNull($t)];
        yield 'list' => [fn (Type $t) => Type::listOf($t)];
        yield 'non-null list of non-null' => [fn (Type $t) => Type::nonNull(Type::listOf(Type::nonNull($t)))];
        yield 'list of list' => [fn (Type $t) => Type::listOf(Type::listOf($t))];
    }

    public function testInterfaceTypedFieldReadsTheInterfaceAnnotation(): void
    {
        $interface = new InterfaceType([
            'name' => 'Node',
            'fields' => ['id' => Type::string()],
            'cacheControl' => ['maxAge' => 45, 'scope' => CacheScope::PRIVATE],
        ]);

        $hint = $this->resolver->forField($this->fieldReturning($interface));

        $this->assertSame(45, $hint->getMaxAge());
        $this->assertSame(CacheScope::PRIVATE, $hint->getScope());
        $this->assertTrue($hint->returnsCompositeType());
    }

    public function testUnionTypedFieldReadsTheUnionAnnotation(): void
    {
        $union = new UnionType([
            'name' => 'SearchResult',
            'types' => [$this->objectType(['maxAge' => 5])],
            'cacheControl' => ['maxAge' => 90],
        ]);

        $hint = $this->resolver->forField($this->fieldReturning($union));

        // The declared union's annotation wins; the member type's is not consulted statically.
        $this->assertSame(90, $hint->getMaxAge());
        $this->assertTrue($hint->returnsCompositeType());
    }

    public function testHintsAreMemoizedPerFieldDefinition(): void
    {
        $field = $this->fieldReturning($this->objectType(['maxAge' => 60]));

        $this->assertSame($this->resolver->forField($field), $this->resolver->forField($field));
    }

    public function testForTypeReadsTheAnnotationDirectly(): void
    {
        $hint = $this->resolver->forType($this->objectType(['maxAge' => 15, 'scope' => CacheScope::PUBLIC]));

        $this->assertSame(15, $hint->getMaxAge());
        $this->assertSame(CacheScope::PUBLIC, $hint->getScope());
    }

    public function testForTypeOnAnUnannotatedType(): void
    {
        $this->assertTrue($this->resolver->forType($this->objectType())->isEmpty());
    }

    private function objectType(?array $cacheControl = null): ObjectType
    {
        $config = [
            'name' => 'Post'.(++$this->typeCounter),
            'fields' => ['id' => Type::string()],
        ];

        if (null !== $cacheControl) {
            $config['cacheControl'] = $cacheControl;
        }

        return new ObjectType($config);
    }

    private function fieldReturning(Type $type, ?array $cacheControl = null): FieldDefinition
    {
        $fieldConfig = ['type' => $type];

        if (null !== $cacheControl) {
            $fieldConfig['cacheControl'] = $cacheControl;
        }

        $parent = new ObjectType([
            'name' => 'Parent'.(++$this->typeCounter),
            'fields' => ['subject' => $fieldConfig],
        ]);

        return $parent->getField('subject');
    }
}
