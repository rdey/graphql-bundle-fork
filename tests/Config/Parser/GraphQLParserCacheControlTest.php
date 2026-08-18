<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser;

use GraphQL\Language\Parser;
use Redeye\GraphQLBundle\Config\Parser\GraphQL\ASTConverter\CacheDirectivesNode;
use Redeye\GraphQLBundle\Config\Parser\GraphQLParser;
use RuntimeException;
use SplFileInfo;
use const DIRECTORY_SEPARATOR;

class GraphQLParserCacheControlTest extends TestCase
{
    public function testParse(): void
    {
        $fileName = __DIR__.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.'graphql'.DIRECTORY_SEPARATOR.'cache-control.graphql';
        $expected = include __DIR__.'/fixtures/graphql/cache-control.php';

        $this->assertContainerAddFileToResources($fileName);
        $config = GraphQLParser::parse(new SplFileInfo($fileName), $this->containerBuilder);

        $this->assertSame($expected, self::cleanConfig($config));
    }

    /**
     * The directive definitions in the fixture must be accepted and then ignored — the bundle
     * supplies the canonical ones itself.
     */
    public function testDirectiveDefinitionsAreNotTurnedIntoTypes(): void
    {
        $fileName = __DIR__.'/fixtures/graphql/cache-control.graphql';
        $config = GraphQLParser::parse(new SplFileInfo($fileName), $this->containerBuilder);

        $this->assertArrayNotHasKey('cacheControl', $config);
        $this->assertArrayNotHasKey('cacheTag', $config);
    }

    /**
     * @dataProvider invalidUsageProvider
     */
    public function testInvalidUsageIsRejected(string $directives, string $expectedMessage): void
    {
        $node = Parser::parse(sprintf('type Foo { bar: String %s }', $directives))->definitions[0]->fields[0];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        CacheDirectivesNode::toConfig($node);
    }

    public function invalidUsageProvider(): iterable
    {
        yield 'maxAge as a string' => [
            '@cacheControl(maxAge: "60")',
            'Argument "maxAge" of @cacheControl must be an Int',
        ];

        yield 'unknown scope' => [
            '@cacheControl(scope: MAYBE)',
            'Argument "scope" of @cacheControl must be one of PUBLIC, PRIVATE, got "MAYBE".',
        ];

        yield 'inheritMaxAge as a string' => [
            '@cacheControl(inheritMaxAge: "yes")',
            'Argument "inheritMaxAge" of @cacheControl must be a Boolean',
        ];

        yield 'maxAge together with inheritMaxAge' => [
            '@cacheControl(maxAge: 5, inheritMaxAge: true)',
            '@cacheControl cannot set both "maxAge" and "inheritMaxAge".',
        ];

        yield 'repeated cacheControl' => [
            '@cacheControl(maxAge: 5) @cacheControl(maxAge: 10)',
            '@cacheControl is not repeatable.',
        ];

        yield 'cacheTag without a format' => [
            '@cacheTag',
            'Argument "format" is required on the @cacheTag directive.',
        ];

        yield 'cacheTag format as an enum' => [
            '@cacheTag(format: POSTS)',
            'Argument "format" of @cacheTag must be a String',
        ];
    }
}
