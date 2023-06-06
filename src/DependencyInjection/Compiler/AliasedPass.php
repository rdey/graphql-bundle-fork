<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DependencyInjection\Compiler;

use GraphQL\Type\Definition\Type;
use Redeye\GraphQLBundle\Definition\Resolver\AliasedInterface;
use Redeye\GraphQLBundle\Definition\Resolver\MutationInterface;
use Redeye\GraphQLBundle\Definition\Resolver\QueryInterface;
use Redeye\GraphQLBundle\Definition\Resolver\ResolverInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use function array_filter;
use function is_subclass_of;

final class AliasedPass implements CompilerPassInterface
{
    private const SERVICE_SUBCLASS_TAG_MAPPING = [
        MutationInterface::class => 'redeye_graphql.mutation',
        QueryInterface::class => 'redeye_graphql.query',
        Type::class => TypeTaggedServiceMappingPass::TAG_NAME,
        // TODO: remove next line in 1.0
        ResolverInterface::class => 'redeye_graphql.resolver',
    ];

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $definitions = $this->filterDefinitions($container->getDefinitions());
        foreach ($definitions as $definition) {
            $this->addDefinitionTagsFromAliases($definition);
        }
    }

    /**
     * @param Definition[] $definitions
     *
     * @return Definition[]
     */
    private function filterDefinitions(array $definitions): array
    {
        return array_filter($definitions, function (Definition $definition) {
            // TODO: remove following if-block in 1.0
            if ($definition->hasTag('redeye_graphql.resolver')) {
                @trigger_error(
                    "The 'redeye_graphql.resolver' tag is deprecated since 0.14 and will be removed in 1.0. Use 'redeye_graphql.query' instead. For more info visit: https://github.com/redeye/GraphQLBundle/issues/775",
                    E_USER_DEPRECATED
                );
            }

            foreach (self::SERVICE_SUBCLASS_TAG_MAPPING as $tagName) {
                if ($definition->hasTag($tagName)) {
                    return is_subclass_of($definition->getClass(), AliasedInterface::class);
                }
            }

            return false;
        });
    }

    private function addDefinitionTagsFromAliases(Definition $definition): void
    {
        /**
         * @var class-string<AliasedInterface> $class
         */
        $class = (string) $definition->getClass();
        $aliases = $class::getAliases();
        /** @var string $tagName */
        $tagName = $this->guessTagName($definition);
        $withMethod = TypeTaggedServiceMappingPass::TAG_NAME !== $tagName;

        foreach ($aliases as $key => $alias) {
            $definition->addTag($tagName, $withMethod ? ['alias' => $alias, 'method' => $key] : ['alias' => $alias]);
        }
    }

    private function guessTagName(Definition $definition): ?string
    {
        $tagName = null;
        foreach (self::SERVICE_SUBCLASS_TAG_MAPPING as $refClassName => $tag) {
            if (is_subclass_of($definition->getClass(), $refClassName)) {
                $tagName = $tag;
                break;
            }
        }

        return $tagName;
    }
}
