<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;

final class IsTypeOf extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'isTypeOf',
            static fn ($className) => "((\$className = $className) && \$value instanceof \$className)",
            static fn ($arguments, $className) => $className && $arguments['parentValue'] instanceof $className
        );
    }
}
