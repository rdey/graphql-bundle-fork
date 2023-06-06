<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;

final class Call extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'call',
            fn (string $target, string $args = '[]') => "$target(...$args)",
            fn ($_, callable $target, array $args) => $target(...$args)
        );
    }
}
