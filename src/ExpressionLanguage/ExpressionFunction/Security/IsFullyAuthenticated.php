<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class IsFullyAuthenticated extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'isFullyAuthenticated',
            fn () => "$this->gqlServices->get('security')->isFullyAuthenticated()",
            fn (array $arguments) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('security')->isFullyAuthenticated()
        );
    }
}
