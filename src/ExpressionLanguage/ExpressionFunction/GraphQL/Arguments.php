<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class Arguments extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'arguments',
            fn ($mapping, $data) => "$this->gqlServices->get('container')->get('redeye_graphql.arguments_transformer')->getArguments($mapping, $data, \$info)",
            static fn (array $arguments, $mapping, $data) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('container')->get('redeye_graphql.arguments_transformer')->getArguments($mapping, $data, $arguments['info'])
        );
    }
}
