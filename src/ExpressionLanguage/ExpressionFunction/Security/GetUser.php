<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class GetUser extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'getUser',
            fn () => "$this->gqlServices->get('security')->getUser()",
            static fn (array $arguments) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('security')->getUser()
        );
    }
}
