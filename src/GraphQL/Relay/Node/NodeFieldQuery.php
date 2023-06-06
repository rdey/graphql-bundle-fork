<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\GraphQL\Relay\Node;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Redeye\GraphQLBundle\Definition\ArgumentInterface;
use Redeye\GraphQLBundle\Definition\Resolver\AliasedInterface;
use Redeye\GraphQLBundle\Definition\Resolver\QueryInterface;

final class NodeFieldQuery implements QueryInterface, AliasedInterface
{
    /**
     * @param mixed $context
     *
     * @return mixed
     */
    public function __invoke(ArgumentInterface $args, $context, ResolveInfo $info, Closure $idFetcherCallback)
    {
        return $idFetcherCallback($args['id'], $context, $info);
    }

    /**
     * {@inheritdoc}
     */
    public static function getAliases(): array
    {
        return ['__invoke' => 'relay_node_field'];
    }
}
