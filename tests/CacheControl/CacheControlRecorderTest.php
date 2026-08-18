<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\CacheControl;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\CacheControl\CacheControlRecorder;
use Redeye\GraphQLBundle\CacheControl\CacheHintResolver;
use Redeye\GraphQLBundle\CacheControl\CacheScope;

/**
 * Covers Apollo's defaulting rules: which fields are uncacheable unless told otherwise, and which
 * stay undecided so they inherit from their ancestors.
 */
class CacheControlRecorderTest extends TestCase
{
    private int $typeCounter = 0;

    public function defaultMaxAgeProvider(): iterable
    {
        yield 'defaultMaxAge 0' => [0];
        yield 'defaultMaxAge 60' => [60];
    }

    /**
     * @dataProvider defaultMaxAgeProvider
     */
    public function testUnannotatedNonRootScalarContributesNothing(int $default): void
    {
        $recorder = $this->recorder($default);
        $recorder->recordField($this->info($this->field(Type::string()), false));

        $this->assertNull($recorder->getPolicy()->getMaxAge());
    }

    /**
     * @dataProvider defaultMaxAgeProvider
     */
    public function testUnannotatedRootScalarGetsTheDefault(int $default): void
    {
        $recorder = $this->recorder($default);
        $recorder->recordField($this->info($this->field(Type::string()), true));

        $this->assertSame($default, $recorder->getPolicy()->getMaxAge());
    }

    /**
     * @dataProvider defaultMaxAgeProvider
     */
    public function testUnannotatedNonRootCompositeGetsTheDefault(int $default): void
    {
        $recorder = $this->recorder($default);
        $recorder->recordField($this->info($this->field($this->objectType()), false));

        $this->assertSame($default, $recorder->getPolicy()->getMaxAge());
    }

    /**
     * @dataProvider defaultMaxAgeProvider
     */
    public function testCompositeWithInheritMaxAgeOnTheTypeContributesNothing(int $default): void
    {
        $recorder = $this->recorder($default);
        $recorder->recordField($this->info($this->field($this->objectType(['inheritMaxAge' => true])), false));

        $this->assertNull($recorder->getPolicy()->getMaxAge());
    }

    /**
     * @dataProvider defaultMaxAgeProvider
     */
    public function testRootFieldGetsTheDefaultEvenWithInheritMaxAge(int $default): void
    {
        // Verified against Apollo's source: the root-field condition is an `||`, so a root field is
        // defaulted regardless of inheritMaxAge. There is no parent to inherit from.
        $recorder = $this->recorder($default);
        $recorder->recordField($this->info($this->field($this->objectType(['inheritMaxAge' => true])), true));

        $this->assertSame($default, $recorder->getPolicy()->getMaxAge());
    }

    /**
     * @dataProvider defaultMaxAgeProvider
     */
    public function testExplicitMaxAgeWins(int $default): void
    {
        $recorder = $this->recorder($default);
        $recorder->recordField($this->info($this->field($this->objectType(), ['maxAge' => 30]), false));

        $this->assertSame(30, $recorder->getPolicy()->getMaxAge());
    }

    /**
     * @dataProvider defaultMaxAgeProvider
     */
    public function testExplicitZeroIsNotUnset(int $default): void
    {
        $recorder = $this->recorder($default);
        $recorder->recordField($this->info($this->field($this->objectType(), ['maxAge' => 0]), false));

        $this->assertSame(0, $recorder->getPolicy()->getMaxAge());
    }

    /**
     * @dataProvider defaultMaxAgeProvider
     */
    public function testScopeOnlyOnANonRootScalarLeavesMaxAgeUndecided(int $default): void
    {
        $recorder = $this->recorder($default);
        $recorder->recordField($this->info($this->field(Type::string(), ['scope' => CacheScope::PRIVATE]), false));

        $this->assertNull($recorder->getPolicy()->getMaxAge());
        $this->assertSame(CacheScope::PRIVATE, $recorder->getPolicy()->getScope());
    }

    public function testFieldInheritMaxAgeContributesNothing(): void
    {
        $recorder = $this->recorder(60);
        $recorder->recordField($this->info(
            $this->field($this->objectType(), ['inheritMaxAge' => true]),
            false
        ));

        $this->assertNull($recorder->getPolicy()->getMaxAge());
    }

    public function testPolicyIsTheMinimumAcrossFields(): void
    {
        $recorder = $this->recorder(0);
        $recorder->recordField($this->info($this->field($this->objectType(), ['maxAge' => 90]), true));
        $recorder->recordField($this->info($this->field($this->objectType(), ['maxAge' => 30]), false));
        $recorder->recordField($this->info($this->field($this->objectType(), ['maxAge' => 60]), false));

        $this->assertSame(30, $recorder->getPolicy()->getMaxAge());
    }

    public function testRecordingStopsOnceFullyRestricted(): void
    {
        $recorder = $this->recorder(0);

        // maxAge 0 + PRIVATE cannot be restricted further.
        $recorder->recordField($this->info(
            $this->field($this->objectType(), ['maxAge' => 0, 'scope' => CacheScope::PRIVATE]),
            true
        ));

        $this->assertTrue($recorder->getPolicy()->isFullyRestricted());

        // Anything after this is ignored, which is what makes the hot path cheap.
        $recorder->recordType($this->objectType(['maxAge' => 500, 'scope' => CacheScope::PUBLIC]));

        $this->assertSame(0, $recorder->getPolicy()->getMaxAge());
        $this->assertSame(CacheScope::PRIVATE, $recorder->getPolicy()->getScope());
    }

    public function testSelfHintedFieldsAreSkipped(): void
    {
        // `_entities` is a root field, so without this exemption it would pin every federated
        // entity fetch at maxAge 0 and no entity response could ever be cached.
        $field = $this->field($this->objectType(), null, [CacheControlRecorder::SELF_HINTED_CONFIG_KEY => true]);

        $recorder = $this->recorder(0);
        $recorder->recordField($this->info($field, true));

        $this->assertNull($recorder->getPolicy()->getMaxAge());
    }

    public function testRecordTypeAppliesTheConcreteTypeHint(): void
    {
        $recorder = $this->recorder(0);
        $recorder->recordType($this->objectType(['maxAge' => 120, 'scope' => CacheScope::PRIVATE]));

        $this->assertSame(120, $recorder->getPolicy()->getMaxAge());
        $this->assertSame(CacheScope::PRIVATE, $recorder->getPolicy()->getScope());
    }

    /**
     * @dataProvider defaultMaxAgeProvider
     */
    public function testRecordTypeFallsBackToTheDefault(int $default): void
    {
        $recorder = $this->recorder($default);
        $recorder->recordType($this->objectType());

        $this->assertSame($default, $recorder->getPolicy()->getMaxAge());
    }

    private function recorder(int $defaultMaxAge): CacheControlRecorder
    {
        return new CacheControlRecorder(new CacheHintResolver(), $defaultMaxAge);
    }

    private function objectType(?array $cacheControl = null): ObjectType
    {
        $config = [
            'name' => 'Node'.(++$this->typeCounter),
            'fields' => ['id' => Type::string()],
        ];

        if (null !== $cacheControl) {
            $config['cacheControl'] = $cacheControl;
        }

        return new ObjectType($config);
    }

    private function field(Type $type, ?array $cacheControl = null, array $extraConfig = []): FieldDefinition
    {
        $fieldConfig = ['type' => $type] + $extraConfig;

        if (null !== $cacheControl) {
            $fieldConfig['cacheControl'] = $cacheControl;
        }

        $parent = new ObjectType([
            'name' => 'Parent'.(++$this->typeCounter),
            'fields' => ['subject' => $fieldConfig],
        ]);

        return $parent->getField('subject');
    }

    private function info(FieldDefinition $field, bool $root): ResolveInfo
    {
        $queryType = new ObjectType([
            'name' => 'Query'.(++$this->typeCounter),
            'fields' => ['dummy' => Type::string()],
        ]);

        return new ResolveInfo(
            $field,
            [],
            $queryType,
            $root ? ['subject'] : ['parent', 'subject'],
            new Schema(['query' => $queryType]),
            [],
            null,
            null,
            []
        );
    }
}
