<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\DependencyInjection;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class Service extends ExpressionFunction
{
    public function __construct($name = 'service')
    {
        parent::__construct(
            $name,
            fn (string $serviceId) => "$this->gqlServices->get('container')->get($serviceId)",
            static fn (array $arguments, $serviceId) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('container')->get($serviceId)
        );
    }
}
