<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\DependencyInjection\Configuration;
use Redeye\GraphQLBundle\Federation\Tracing\TraceErrorFilter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class InlineTraceConfigurationTest extends TestCase
{
    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(false), $configs);
    }

    public function testDisabledByDefault(): void
    {
        $config = $this->process([[]]);

        $this->assertFalse($config['inline_trace']['enabled']);
    }

    public function testEnabledByDefaultForAFederatedSchema(): void
    {
        $config = $this->process([['federation' => true]]);

        $this->assertTrue($config['inline_trace']['enabled']);
    }

    public function testCanBeDisabledOnAFederatedSchema(): void
    {
        $config = $this->process([['federation' => true, 'inline_trace' => false]]);

        $this->assertFalse($config['inline_trace']['enabled']);
    }

    public function testCanBeEnabledOnANonFederatedSchema(): void
    {
        $config = $this->process([['inline_trace' => true]]);

        $this->assertTrue($config['inline_trace']['enabled']);
    }

    /**
     * Configuring options must not be a back door to switching the feature on.
     */
    public function testSettingOptionsAloneDoesNotEnableItOnANonFederatedSchema(): void
    {
        $config = $this->process([['inline_trace' => ['include_errors' => 'unmodified']]]);

        $this->assertFalse($config['inline_trace']['enabled']);
        $this->assertSame('unmodified', $config['inline_trace']['include_errors']);
    }

    /**
     * Symfony normalizes each config fragment on its own and only then merges them, so the
     * federation default has to be resolved after the merge. A `beforeNormalization` on the root
     * node would see these two fragments separately and get this wrong.
     */
    public function testInheritsFederationAcrossSeparateConfigFragments(): void
    {
        $config = $this->process([
            ['federation' => true],
            ['inline_trace' => ['include_errors' => 'unmodified']],
        ]);

        $this->assertTrue($config['inline_trace']['enabled']);
        $this->assertSame('unmodified', $config['inline_trace']['include_errors']);
    }

    public function testDefaultsToMaskedErrors(): void
    {
        $config = $this->process([['federation' => true]]);

        $this->assertSame(TraceErrorFilter::MASKED, $config['inline_trace']['include_errors']);
    }

    public function testDefaultsToNoTransformerService(): void
    {
        $config = $this->process([['federation' => true]]);

        $this->assertNull($config['inline_trace']['transformer_service']);
    }

    public function testAcceptsATransformerService(): void
    {
        $config = $this->process([['inline_trace' => ['transformer_service' => 'App\\MyTransformer']]]);

        $this->assertSame('App\\MyTransformer', $config['inline_trace']['transformer_service']);
    }

    public function testNullEnablesIt(): void
    {
        $config = $this->process([['inline_trace' => null]]);

        $this->assertTrue($config['inline_trace']['enabled']);
    }

    public function testRejectsAnUnknownIncludeErrorsMode(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['inline_trace' => ['include_errors' => 'bogus']]]);
    }
}
