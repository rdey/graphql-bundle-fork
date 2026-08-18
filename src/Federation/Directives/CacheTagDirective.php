<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation\Directives;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\Type;

/**
 * Tags data for targeted cache invalidation.
 *
 * Introduced in Federation v2.12 and interpreted by the router, not by this subgraph: the only
 * thing that has to happen here is that the directive survives into the published SDL.
 */
class CacheTagDirective extends Directive
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'cacheTag',
            'isRepeatable' => true,
            'locations' => [
                DirectiveLocation::FIELD_DEFINITION,
                DirectiveLocation::OBJECT,
            ],
            'args' => [
                new FieldArgument([
                    'name' => 'format',
                    'type' => Type::nonNull(Type::string()),
                ]),
            ],
        ]);
    }
}
