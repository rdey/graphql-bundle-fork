<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class IsAnonymous extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'isAnonymous',
            fn () => "$this->gqlServices->get('security')->isAnonymous()",
            static fn (array $arguments) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('security')->isAnonymous()
        );
    }
}
