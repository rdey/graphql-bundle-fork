<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation\Types;

use GraphQL\Type\Definition\EnumType;
use Redeye\GraphQLBundle\CacheControl\CacheScope;

/**
 * The `scope` argument of the `@cacheControl` directive.
 */
class CacheControlScopeType extends EnumType
{
    public function __construct()
    {
        parent::__construct([
            'name' => self::getTypeName(),
            'values' => [
                CacheScope::PUBLIC => ['value' => CacheScope::PUBLIC],
                CacheScope::PRIVATE => [
                    'value' => CacheScope::PRIVATE,
                    'description' => 'The value is specific to a single user.',
                ],
            ],
        ]);
    }

    public static function getTypeName(): string
    {
        return 'CacheControlScope';
    }
}
