<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Definition\Resolver;

interface AliasedInterface
{
    /**
     * Returns methods aliases.
     *
     * For instance:
     * array('myMethod' => 'myAlias')
     */
    public static function getAliases(): array;
}
