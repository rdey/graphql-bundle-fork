<?php

namespace Redeye\GraphQLBundle\Federation\Directives;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\Directive;
use GraphQL\Language\DirectiveLocation;

class OverrideDirective extends Directive
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'override',
            'locations' => [DirectiveLocation::FIELD_DEFINITION],
            'args' => [
                "from" => [
                    'type' => Type::nonNull(Type::string())
                ]
            ]
        ]);
    }
}
