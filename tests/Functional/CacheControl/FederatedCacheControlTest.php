<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\CacheControl;

use Redeye\GraphQLBundle\Federation\Utils\FederatedSchemaPrinter;
use Redeye\GraphQLBundle\Request\Executor;
use Redeye\GraphQLBundle\Tests\Functional\TestCase;
use function json_encode;

/**
 * The subgraph half of the feature: the SDL the router composes, and the `_entities` field it
 * calls for every entity fetch.
 */
class FederatedCacheControlTest extends TestCase
{
    private const TEST_CASE = 'cacheControlFederation';

    private static function sdl(): string
    {
        static::createClientAuthenticated('ryan', self::TEST_CASE);

        /** @var Executor $executor */
        $executor = static::getContainer()->get('redeye_graphql.request_executor');

        return FederatedSchemaPrinter::doPrint($executor->getSchema('default'));
    }

    public function testLinksTheFederationVersionThatDefinesCacheTag(): void
    {
        $sdl = self::sdl();

        $this->assertStringContainsString('https://specs.apollo.dev/federation/v2.12', $sdl, $sdl);
        $this->assertStringContainsString('"@cacheTag"', $sdl, $sdl);
    }

    /**
     * @cacheTag comes from the @link import, so a local definition would be a composition error.
     */
    public function testCacheTagHasNoLocalDefinition(): void
    {
        $this->assertStringNotContainsString('directive @cacheTag', self::sdl());
    }

    /**
     * @cacheControl is not a federation directive, so it needs one — and exactly one.
     */
    public function testCacheControlIsDeclaredExactlyOnce(): void
    {
        $sdl = self::sdl();

        $this->assertSame(1, substr_count($sdl, 'directive @cacheControl'), $sdl);
        $this->assertSame(1, substr_count($sdl, 'enum CacheControlScope'), $sdl);
    }

    public function testFieldDirectivesArePrinted(): void
    {
        $sdl = self::sdl();

        $this->assertStringContainsString('@cacheControl(maxAge: 60)', $sdl, $sdl);
        $this->assertStringContainsString('@cacheTag(format: "posts")', $sdl, $sdl);
        $this->assertStringContainsString('@cacheControl(inheritMaxAge: true)', $sdl, $sdl);
    }

    public function testEntityTypeDirectivesArePrinted(): void
    {
        $sdl = self::sdl();

        $this->assertStringContainsString('@cacheControl(maxAge: 120)', $sdl, $sdl);
        $this->assertStringContainsString('@cacheTag(format: "post-{$key.id}")', $sdl, $sdl);
        $this->assertStringContainsString('@cacheTag(format: "post")', $sdl, $sdl);
    }

    public function testObjectInterfaceAndUnionDirectivesArePrinted(): void
    {
        $sdl = self::sdl();

        $this->assertMatchesRegularExpression('/type Author @cacheControl\(maxAge: 300, scope: PRIVATE\)/', $sdl, $sdl);
        $this->assertMatchesRegularExpression('/interface Timestamped @cacheControl\(maxAge: 45\)/', $sdl, $sdl);
        $this->assertMatchesRegularExpression('/union Content @cacheControl\(maxAge: 15\) = /', $sdl, $sdl);
    }

    /**
     * Without the `_entities` exemption this would be no-store, since `_entities` is a root field.
     */
    public function testEntitiesQueryTakesTheHintOfTheConcreteType(): void
    {
        $client = static::createClientAuthenticated('ryan', self::TEST_CASE);

        $query = 'query($reps: [_Any!]!) { _entities(representations: $reps) { ... on Post { title } } }';
        $variables = ['reps' => [['__typename' => 'Post', 'id' => '1']]];

        $client->request('GET', '/', ['query' => $query, 'variables' => json_encode($variables)]);

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame([['title' => 'First']], $result['data']['_entities'] ?? null, json_encode($result));

        $this->assertSame('max-age=120, public', $client->getResponse()->headers->get('Cache-Control'));
    }
}
