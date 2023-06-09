<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\DependencyInjection\Compiler;

use InvalidArgumentException;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\QueryTaggedServiceMappingPass;
use Redeye\GraphQLBundle\Resolver\QueryResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ResolverTaggedServiceMappingPassTest extends TestCase
{
    /** @var ContainerBuilder */
    private $container;

    public function setUp(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('injected_service', new Definition(FakeInjectedService::class));

        $container->register('redeye_graphql.query_resolver', QueryResolver::class);

        $this->container = $container;
    }

    private function addCompilerPassesAndCompile(): void
    {
        $this->container->addCompilerPass(new QueryTaggedServiceMappingPass());
        $this->container->addCompilerPass(new FakeCompilerPass());
        $this->container->compile();
    }

    /**
     * @group legacy
     */
    public function testCompilationWorksPassConfigDirective(): void
    {
        $testResolver = new Definition(ResolverTestService::class);
        $testResolver
            ->setPublic(true)
            ->addTag('redeye_graphql.query', [
                'alias' => 'test_resolver', 'method' => 'doSomethingWithContainer',
            ]);

        $this->container->setDefinition('test_resolver', $testResolver);

        $this->addCompilerPassesAndCompile();

        $this->assertTrue($this->container->has('test_resolver'));
    }

    public function testTagAliasIsValid(): void
    {
        $testResolver = new Definition(ResolverTestService::class);
        $testResolver
            ->addTag('redeye_graphql.query', [
                'alias' => false, 'method' => 'doSomethingWithContainer',
            ]);

        $this->container->setDefinition('test_resolver', $testResolver);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service tagged "test_resolver" must have valid "alias" argument.');

        $this->addCompilerPassesAndCompile();
    }

    public function testTagMethodIsValid(): void
    {
        $testResolver = new Definition(ResolverTestService::class);
        $testResolver
            ->addTag('redeye_graphql.query', [
                'alias' => 'test_resolver', 'method' => false,
            ]);

        $this->container->setDefinition('test_resolver', $testResolver);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service tagged "test_resolver" must have valid "method" argument.');

        $this->addCompilerPassesAndCompile();
    }
}
