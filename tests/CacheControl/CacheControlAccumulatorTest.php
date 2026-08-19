<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\CacheControl;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\CacheControl\CacheControlAccumulator;
use Redeye\GraphQLBundle\CacheControl\CacheHintResolver;
use Redeye\GraphQLBundle\CacheControl\CacheScope;
use Symfony\Component\HttpFoundation\Response;

class CacheControlAccumulatorTest extends TestCase
{
    /**
     * What a Symfony Response carries when nothing has touched its Cache-Control header.
     */
    private const UNTOUCHED = 'no-cache, private';

    /**
     * Symfony appends `, private` to any Cache-Control lacking public/private/s-maxage.
     */
    private const NO_STORE = 'no-store, private';

    private int $typeCounter = 0;

    public function testDisabledLeavesTheHeaderAlone(): void
    {
        $accumulator = $this->accumulator(false);
        $this->runOperation($accumulator, ['maxAge' => 60]);

        $this->assertSame(self::UNTOUCHED, $this->header($accumulator));
    }

    public function testCacheableResponse(): void
    {
        $accumulator = $this->accumulator();
        $this->runOperation($accumulator, ['maxAge' => 60]);

        $this->assertSame('max-age=60, public', $this->header($accumulator));
    }

    public function testPrivateScope(): void
    {
        $accumulator = $this->accumulator();
        $this->runOperation($accumulator, ['maxAge' => 60, 'scope' => CacheScope::PRIVATE]);

        $this->assertSame('max-age=60, private', $this->header($accumulator));
    }

    public function testZeroMaxAgeIsUncacheable(): void
    {
        $accumulator = $this->accumulator();
        $this->runOperation($accumulator, ['maxAge' => 0]);

        $this->assertSame(self::NO_STORE, $this->header($accumulator));
    }

    public function testNoOperationAtAllIsUncacheable(): void
    {
        $this->assertSame(self::NO_STORE, $this->header($this->accumulator()));
    }

    public function testErrorsMakeTheResponseUncacheable(): void
    {
        $accumulator = $this->accumulator();
        $this->runOperation($accumulator, ['maxAge' => 60], true);

        $this->assertSame(self::NO_STORE, $this->header($accumulator));
    }

    public function testErrorsInOneBatchedOperationPoisonTheWholeResponse(): void
    {
        $accumulator = $this->accumulator();
        $this->runOperation($accumulator, ['maxAge' => 60], true);
        $this->runOperation($accumulator, ['maxAge' => 60]);

        $this->assertSame(self::NO_STORE, $this->header($accumulator));
    }

    public function testBatchTakesTheMostRestrictiveMaxAge(): void
    {
        $accumulator = $this->accumulator();
        $this->runOperation($accumulator, ['maxAge' => 60]);
        $this->runOperation($accumulator, ['maxAge' => 30]);

        $this->assertSame('max-age=30, public', $this->header($accumulator));
    }

    public function testBatchTakesTheMostRestrictiveScope(): void
    {
        $accumulator = $this->accumulator();
        $this->runOperation($accumulator, ['maxAge' => 60]);
        $this->runOperation($accumulator, ['maxAge' => 60, 'scope' => CacheScope::PRIVATE]);

        $this->assertSame('max-age=60, private', $this->header($accumulator));
    }

    public function testIfCacheableLeavesTheHeaderAloneInsteadOfNoStore(): void
    {
        $accumulator = $this->accumulator(true, 0, CacheControlAccumulator::HEADERS_IF_CACHEABLE);
        $this->runOperation($accumulator, ['maxAge' => 0]);

        $this->assertSame(self::UNTOUCHED, $this->header($accumulator));
    }

    public function testIfCacheableStillEmitsACacheableHeader(): void
    {
        $accumulator = $this->accumulator(true, 0, CacheControlAccumulator::HEADERS_IF_CACHEABLE);
        $this->runOperation($accumulator, ['maxAge' => 60]);

        $this->assertSame('max-age=60, public', $this->header($accumulator));
    }

    public function testNeverLeavesTheHeaderAloneEvenWhenCacheable(): void
    {
        $accumulator = $this->accumulator(true, 0, CacheControlAccumulator::HEADERS_NEVER);
        $this->runOperation($accumulator, ['maxAge' => 60]);

        $this->assertSame(self::UNTOUCHED, $this->header($accumulator));
    }

    public function testResetClearsThePolicyAndTheErrorFlag(): void
    {
        $accumulator = $this->accumulator();
        $this->runOperation($accumulator, ['maxAge' => 60], true);

        $accumulator->reset();
        $this->runOperation($accumulator, ['maxAge' => 30]);

        $this->assertSame('max-age=30, public', $this->header($accumulator));
    }

    public function testDefaultMaxAgeIsUsedForUnannotatedTypes(): void
    {
        $accumulator = $this->accumulator(true, 45);
        $this->runOperation($accumulator, null);

        $this->assertSame('max-age=45, public', $this->header($accumulator));
    }

    private function accumulator(
        bool $enabled = true,
        int $defaultMaxAge = 0,
        string $mode = CacheControlAccumulator::HEADERS_ALWAYS
    ): CacheControlAccumulator {
        return new CacheControlAccumulator(new CacheHintResolver(), $enabled, $defaultMaxAge, $mode);
    }

    private function runOperation(CacheControlAccumulator $accumulator, ?array $cacheControl, bool $hasErrors = false): void
    {
        $accumulator->startOperation()->recordType($this->objectType($cacheControl));
        $accumulator->finishOperation($hasErrors);
    }

    private function objectType(?array $cacheControl): ObjectType
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

    private function header(CacheControlAccumulator $accumulator): ?string
    {
        $response = new Response();
        $accumulator->applyTo($response);

        return $response->headers->get('Cache-Control');
    }
}
