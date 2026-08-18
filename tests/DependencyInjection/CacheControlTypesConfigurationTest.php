<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\DependencyInjection\TypesConfiguration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The YAML authoring path: every mapping format ends up in this config tree, so this is where the
 * shape of `cacheControl` and `cacheTags` is actually enforced.
 */
class CacheControlTypesConfigurationTest extends TestCase
{
    public function testFieldLevelCacheControl(): void
    {
        $config = $this->process([
            'Query' => [
                'type' => 'object',
                'config' => [
                    'fields' => [
                        'posts' => [
                            'type' => '[Post]',
                            'cacheControl' => ['maxAge' => 60, 'scope' => 'PRIVATE'],
                            'cacheTags' => ['posts'],
                        ],
                    ],
                ],
            ],
        ]);

        $field = $config['Query']['config']['fields']['posts'];

        $this->assertSame(['maxAge' => 60, 'scope' => 'PRIVATE'], $field['cacheControl']);
        $this->assertSame(['posts'], $field['cacheTags']);
    }

    public function testTypeLevelCacheControl(): void
    {
        $config = $this->process([
            'Post' => [
                'type' => 'object',
                'config' => [
                    'cacheControl' => ['inheritMaxAge' => true],
                    'cacheTags' => 'post',
                    'fields' => ['id' => ['type' => 'ID']],
                ],
            ],
        ]);

        $this->assertSame(['inheritMaxAge' => true], $config['Post']['config']['cacheControl']);
        // A single tag may be written unwrapped.
        $this->assertSame(['post'], $config['Post']['config']['cacheTags']);
    }

    public function testUnionCacheControl(): void
    {
        $config = $this->process([
            'Content' => [
                'type' => 'union',
                'config' => [
                    'types' => ['Post'],
                    'cacheControl' => ['maxAge' => 15],
                ],
            ],
        ]);

        $this->assertSame(['maxAge' => 15], $config['Content']['config']['cacheControl']);
    }

    /**
     * Unset must stay distinguishable from 0 / PUBLIC / false, or every type in the schema would
     * look annotated and inheritance would break.
     */
    public function testAbsentCacheControlIsNotMaterialised(): void
    {
        $config = $this->process([
            'Post' => [
                'type' => 'object',
                'config' => ['fields' => ['id' => ['type' => 'ID']]],
            ],
        ]);

        $this->assertArrayNotHasKey('cacheControl', $config['Post']['config']);
        $this->assertArrayNotHasKey('cacheTags', $config['Post']['config']);
        $this->assertArrayNotHasKey('cacheTags', $config['Post']['config']['fields']['id']);
    }

    public function testMaxAgeAndInheritMaxAgeAreMutuallyExclusive(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"cacheControl" cannot set both "maxAge" and "inheritMaxAge".');

        $this->process([
            'Post' => [
                'type' => 'object',
                'config' => [
                    'cacheControl' => ['maxAge' => 5, 'inheritMaxAge' => true],
                    'fields' => ['id' => ['type' => 'ID']],
                ],
            ],
        ]);
    }

    public function testUnknownScopeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'Post' => [
                'type' => 'object',
                'config' => [
                    'cacheControl' => ['scope' => 'MAYBE'],
                    'fields' => ['id' => ['type' => 'ID']],
                ],
            ],
        ]);
    }

    public function testNegativeMaxAgeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'Post' => [
                'type' => 'object',
                'config' => [
                    'cacheControl' => ['maxAge' => -1],
                    'fields' => ['id' => ['type' => 'ID']],
                ],
            ],
        ]);
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new TypesConfiguration(), [$config]);
    }
}
