<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle;

use Redeye\GraphQLBundle\DependencyInjection\Compiler\AliasedPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\ConfigParserPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\ExpressionFunctionPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\GraphQLServicesPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\MutationTaggedServiceMappingTaggedPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\QueryTaggedServiceMappingPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\ResolverMapTaggedServiceMappingPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\ResolverMethodAliasesPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\ResolverTaggedServiceMappingPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\TypeGeneratorPass;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\TypeTaggedServiceMappingPass;
use Redeye\GraphQLBundle\DependencyInjection\RedeyeGraphQLExtension;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class RedeyeGraphQLBundle extends Bundle
{
    public function boot(): void
    {
        if ($this->container->has('redeye_graphql.cache_compiler')) {
            $this->container->get('redeye_graphql.cache_compiler')->loadClasses(); // @phpstan-ignore-line
        }
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        //TypeGeneratorPass must be before TypeTaggedServiceMappingPass
        $container->addCompilerPass(new ConfigParserPass());
        $container->addCompilerPass(new GraphQLServicesPass());
        $container->addCompilerPass(new ExpressionFunctionPass());
        $container->addCompilerPass(new ResolverMethodAliasesPass());
        $container->addCompilerPass(new AliasedPass());
        $container->addCompilerPass(new ResolverMapTaggedServiceMappingPass());
        $container->addCompilerPass(new TypeGeneratorPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new TypeTaggedServiceMappingPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new QueryTaggedServiceMappingPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new MutationTaggedServiceMappingTaggedPass(), PassConfig::TYPE_BEFORE_REMOVING);
        // TODO: remove next line in 1.0
        $container->addCompilerPass(new ResolverTaggedServiceMappingPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new RedeyeGraphQLExtension();
        }

        return $this->extension;
    }
}
