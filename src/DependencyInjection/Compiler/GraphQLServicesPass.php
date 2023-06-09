<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DependencyInjection\Compiler;

use InvalidArgumentException;
use Redeye\GraphQLBundle\Definition\GraphQLServices;
use Redeye\GraphQLBundle\Generator\TypeGenerator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use function is_string;
use function sprintf;

final class GraphQLServicesPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds('redeye_graphql.service', true);

        // TODO: remove following if-block in 1.0
        if (count($deprecatedTaggedServices = $container->findTaggedServiceIds('redeye_graphql.global_variable', true)) > 0) {
            @trigger_error(
                "The tag 'redeye_graphql.global_variable' is deprecated since 0.14 and will be removed in 1.0. Use 'redeye_graphql.service' instead. For more info visit: https://github.com/redeye/GraphQLBundle/issues/775",
                E_USER_DEPRECATED
            );

            $taggedServices = array_merge($taggedServices, $deprecatedTaggedServices);
        }

        $locateableServices = [];
        $expressionLanguageDefinition = $container->findDefinition('redeye_graphql.expression_language');

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                if (empty($attributes['alias']) || !is_string($attributes['alias'])) {
                    throw new InvalidArgumentException(
                        sprintf('Service "%s" tagged "redeye_graphql.service" should have a valid "alias" attribute.', $id)
                    );
                }
                $locateableServices[$attributes['alias']] = new Reference($id);

                $isPublic = !isset($attributes['public']) || $attributes['public'];
                if ($isPublic) {
                    $expressionLanguageDefinition->addMethodCall(
                        'addGlobalName',
                        [
                            sprintf(TypeGenerator::GRAPHQL_SERVICES.'->get(\'%s\')', $attributes['alias']),
                            $attributes['alias'],
                        ]
                    );
                }
            }
        }
        $locateableServices['container'] = new Reference('service_container');

        $container->findDefinition(GraphQLServices::class)->addArgument($locateableServices);
    }
}
