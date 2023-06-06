<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\GraphQL\Relay\Node;

use GraphQL\Type\Definition\ResolveInfo;
use Redeye\GraphQLBundle\Definition\Resolver\AliasedInterface;
use Redeye\GraphQLBundle\Definition\Resolver\QueryInterface;
use Redeye\GraphQLBundle\Relay\Node\GlobalId;
use Redeye\GraphQLBundle\Resolver\FieldResolver;

final class GlobalIdFieldQuery implements QueryInterface, AliasedInterface
{
    /**
     * @param object|array    $obj
     * @param int|string|null $idValue
     */
    public function __invoke($obj, ResolveInfo $info, $idValue, ?string $typeName): string
    {
        return GlobalId::toGlobalId(
            !empty($typeName) ? $typeName : $info->parentType->name,
            $idValue ? $idValue : FieldResolver::valueFromObjectOrArray($obj, 'id')
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function getAliases(): array
    {
        return ['__invoke' => 'relay_globalid_field'];
    }
}
