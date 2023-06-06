<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class IsRememberMe extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'isRememberMe',
            fn () => "$this->gqlServices->get('security')->isRememberMe()",
            static fn (array $arguments) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('security')->isRememberMe()
        );
    }
}
