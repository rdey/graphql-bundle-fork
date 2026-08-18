<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\CacheControl;

use Redeye\GraphQLBundle\Tests\Functional\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use function json_encode;

/**
 * End-to-end: does a real HTTP response carry the Cache-Control header the schema asks for.
 */
class CacheControlTest extends TestCase
{
    /**
     * Symfony appends `, private` to any Cache-Control that names neither public, private nor
     * s-maxage, so an uncacheable response reads like this rather than a bare `no-store`.
     */
    private const NO_STORE = 'no-store, private';

    /**
     * What a response carries when the bundle has not touched the header.
     */
    private const UNTOUCHED = 'no-cache, private';

    public function testCacheableQuery(): void
    {
        $this->assertCacheControl('max-age=60, public', '{ posts { id title } }');
    }

    public function testTypeLevelHintAppliesToAnUnannotatedField(): void
    {
        $this->assertCacheControl('max-age=120, public', '{ featured { title } }');
    }

    public function testPrivateScope(): void
    {
        $this->assertCacheControl('max-age=30, private', '{ me { id email } }');
    }

    public function testUnannotatedRootFieldIsUncacheable(): void
    {
        $this->assertCacheControl(self::NO_STORE, '{ uncached }');
    }

    public function testTheMostRestrictiveFieldWins(): void
    {
        $this->assertCacheControl('max-age=30, public', '{ posts { id } shortLived }');
    }

    public function testOneUncacheableFieldPoisonsTheQuery(): void
    {
        $this->assertCacheControl(self::NO_STORE, '{ posts { id } uncached }');
    }

    public function testAnErroredResponseIsUncacheable(): void
    {
        $client = self::createClientAuthenticated('ryan', 'cacheControl');
        $result = self::sendRequest($client, '{ boom }', true);

        $this->assertNotEmpty($result['errors'] ?? [], 'the query was supposed to fail');
        $this->assertSame(self::NO_STORE, $this->header($client));
    }

    public function testBatchTakesTheMostRestrictiveOperation(): void
    {
        $client = $this->sendBatch('cacheControl', [
            ['id' => 'a', 'query' => '{ posts { id } }'],
            ['id' => 'b', 'query' => '{ shortLived }'],
        ]);

        $this->assertSame('max-age=30, public', $this->header($client));
    }

    public function testBatchWithOneUncacheableOperation(): void
    {
        $client = $this->sendBatch('cacheControl', [
            ['id' => 'a', 'query' => '{ posts { id } }'],
            ['id' => 'b', 'query' => '{ uncached }'],
        ]);

        $this->assertSame(self::NO_STORE, $this->header($client));
    }

    public function testIfCacheableLeavesUncacheableResponsesAlone(): void
    {
        $this->assertCacheControl(self::UNTOUCHED, '{ uncached }', 'cacheControlIfCacheable');
    }

    public function testIfCacheableStillAnnotatesCacheableResponses(): void
    {
        $this->assertCacheControl('max-age=60, public', '{ posts { id } }', 'cacheControlIfCacheable');
    }

    /**
     * Existing consumers of this bundle must see no change until they opt in.
     */
    public function testDisabledNeverTouchesTheHeader(): void
    {
        $this->assertCacheControl(self::UNTOUCHED, '{ posts { id } }', 'cacheControlDisabled');
        $this->assertCacheControl(self::UNTOUCHED, '{ uncached }', 'cacheControlDisabled');
    }

    private function assertCacheControl(string $expected, string $query, string $testCase = 'cacheControl'): void
    {
        $client = self::createClientAuthenticated('ryan', $testCase);
        $result = self::sendRequest($client, $query, true);

        $this->assertArrayNotHasKey('errors', $result, json_encode($result));
        $this->assertSame($expected, $this->header($client));
    }

    private function sendBatch(string $testCase, array $queries): KernelBrowser
    {
        $client = self::createClientAuthenticated('ryan', $testCase);
        $client->request('POST', '/batch', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($queries));

        return $client;
    }

    private function header(KernelBrowser $client): ?string
    {
        return $client->getResponse()->headers->get('Cache-Control');
    }
}
