<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\DependencyInjection;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class Parameter extends ExpressionFunction
{
    public function __construct($name = 'parameter')
    {
        parent::__construct(
            $name,
            fn (string $value) => "$this->gqlServices->get('container')->getParameter($value)",
            static fn (array $arguments, $paramName) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('container')->getParameter($paramName)
        );
    }
}
