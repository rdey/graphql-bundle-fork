<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ExpressionFunctionPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $definition = $container->findDefinition('redeye_graphql.expression_language');
        $taggedServices = $container->findTaggedServiceIds('redeye_graphql.expression_function', true);

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addFunction', [new Reference($id)]);
        }
    }
}
