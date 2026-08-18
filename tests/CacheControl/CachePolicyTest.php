<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\CacheControl;

use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\CacheControl\CachePolicy;
use Redeye\GraphQLBundle\CacheControl\CacheScope;

class CachePolicyTest extends TestCase
{
    public function testStartsUndecidedAndUncacheable(): void
    {
        $policy = new CachePolicy();

        $this->assertNull($policy->getMaxAge());
        $this->assertNull($policy->getScope());
        $this->assertFalse($policy->isCacheable());
    }

    public function testKeepsTheSmallestMaxAge(): void
    {
        $policy = new CachePolicy();
        $policy->restrict(60, null);
        $policy->restrict(30, null);
        $policy->restrict(90, null);

        $this->assertSame(30, $policy->getMaxAge());
    }

    public function testANullMaxAgeContributesNothing(): void
    {
        // This is how "inherit the parent's maxAge" works: an undecided field must not lower the
        // policy, so whatever its ancestors decided still stands.
        $policy = new CachePolicy();
        $policy->restrict(60, null);
        $policy->restrict(null, null);

        $this->assertSame(60, $policy->getMaxAge());
    }

    public function testPrivateWinsAndIsSticky(): void
    {
        $policy = new CachePolicy();
        $policy->restrict(null, CacheScope::PUBLIC);
        $policy->restrict(null, CacheScope::PRIVATE);
        $policy->restrict(null, CacheScope::PUBLIC);

        $this->assertSame(CacheScope::PRIVATE, $policy->getScope());
    }

    public function testANullScopeContributesNothing(): void
    {
        $policy = new CachePolicy();
        $policy->restrict(null, CacheScope::PRIVATE);
        $policy->restrict(null, null);

        $this->assertSame(CacheScope::PRIVATE, $policy->getScope());
    }

    public function testZeroMaxAgeIsNotCacheable(): void
    {
        $policy = new CachePolicy();
        $policy->restrict(0, null);

        $this->assertSame(0, $policy->getMaxAge());
        $this->assertFalse($policy->isCacheable());
    }

    public function testIsCacheableOnlyAboveZero(): void
    {
        $policy = new CachePolicy();
        $policy->restrict(1, null);

        $this->assertTrue($policy->isCacheable());
    }

    public function testIsFullyRestrictedNeedsBothMaxAgeZeroAndPrivate(): void
    {
        $policy = new CachePolicy();
        $policy->restrict(0, null);
        $this->assertFalse($policy->isFullyRestricted(), 'scope can still degrade to PRIVATE');

        $policy->restrict(null, CacheScope::PRIVATE);
        $this->assertTrue($policy->isFullyRestricted());
    }

    public function testRestrictByAnotherPolicy(): void
    {
        $a = new CachePolicy();
        $a->restrict(60, CacheScope::PUBLIC);

        $b = new CachePolicy();
        $b->restrict(30, CacheScope::PRIVATE);

        $a->restrictBy($b);

        $this->assertSame(30, $a->getMaxAge());
        $this->assertSame(CacheScope::PRIVATE, $a->getScope());
    }

    public function testReset(): void
    {
        $policy = new CachePolicy();
        $policy->restrict(30, CacheScope::PRIVATE);
        $policy->reset();

        $this->assertNull($policy->getMaxAge());
        $this->assertNull($policy->getScope());
    }

    public function testUnsetScopeRendersAsPublic(): void
    {
        $this->assertSame('public', CacheScope::toHeaderToken(null));
        $this->assertSame('public', CacheScope::toHeaderToken(CacheScope::PUBLIC));
        $this->assertSame('private', CacheScope::toHeaderToken(CacheScope::PRIVATE));
    }
}
