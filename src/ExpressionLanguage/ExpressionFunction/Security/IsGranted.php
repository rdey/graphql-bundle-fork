<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class IsGranted extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'isGranted',
            fn ($attributes, $object = 'null') => "$this->gqlServices->get('security')->isGranted($attributes, $object)",
            static fn (array $arguments, $attributes, $object = null) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('security')->isGranted($attributes, $object)
        );
    }
}
