<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class HasAnyRole extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'hasAnyRole',
            fn ($roles) => "$this->gqlServices->get('security')->hasAnyRole($roles)",
            static fn (array $arguments, $roles) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('security')->hasAnyRole($roles)
        );
    }
}
