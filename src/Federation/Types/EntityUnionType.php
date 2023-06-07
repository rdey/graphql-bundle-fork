<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation\Types;

use GraphQL\Type\Definition\UnionType;
use Redeye\GraphQLBundle\Federation\EntityTypeResolver\EntityTypeResolverInterface;

/**
 * The union of all entities defined within this schema.
 */
class EntityUnionType extends UnionType
{

    /**
     * @param array|callable $entityTypes all entity types or a callable to retrieve them
     */
    public function __construct($entityTypes, EntityTypeResolverInterface $entityTypeResolver)
    {
        $config = [
            'name' => self::getTypeName(),
            'types' => is_callable($entityTypes)
                ? fn () => array_values($entityTypes())
                : array_values($entityTypes),
            'resolveType' => $entityTypeResolver,

        ];
        parent::__construct($config);
    }

    public static function getTypeName(): string
    {
        return '_Entity';
    }
}
