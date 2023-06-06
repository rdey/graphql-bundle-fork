<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class HasPermission extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'hasPermission',
            fn ($object, $permission) => "$this->gqlServices->get('security')->hasPermission($object, $permission)",
            static fn (array $arguments, $object, $permission) => $arguments[TypeGenerator::GRAPHQL_SERVICES]->get('security')->hasPermission($object, $permission)
        );
    }
}
