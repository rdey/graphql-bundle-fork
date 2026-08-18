<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation\Directives;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\Type;
use Redeye\GraphQLBundle\Federation\Types\CacheControlScopeType;

/**
 * Tells this subgraph how long a response containing the annotated field or type may be cached.
 *
 * Unlike the federation directives, this one is interpreted here rather than by the router: it is
 * what produces the `Cache-Control` response header the router then reads.
 */
class CacheControlDirective extends Directive
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'cacheControl',
            'locations' => [
                DirectiveLocation::FIELD_DEFINITION,
                DirectiveLocation::OBJECT,
                DirectiveLocation::IFACE,
                DirectiveLocation::UNION,
            ],
            'args' => [
                new FieldArgument([
                    'name' => 'maxAge',
                    'type' => Type::int(),
                ]),
                new FieldArgument([
                    'name' => 'scope',
                    'type' => new CacheControlScopeType(),
                ]),
                new FieldArgument([
                    'name' => 'inheritMaxAge',
                    'type' => Type::boolean(),
                ]),
            ],
        ]);
    }
}
