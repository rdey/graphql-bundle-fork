<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;

final class NewObject extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'newObject',
            static fn ($className, $args = '[]') => "(new \\ReflectionClass($className))->newInstanceArgs($args)",
            static fn ($arguments, $className, $args = []) => new $className(...$args)
        );
    }
}
