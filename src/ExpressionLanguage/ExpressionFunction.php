<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage;

use Redeye\GraphQLBundle\ExpressionLanguage\Exception\EvaluatorIsNotAllowedException;
use Redeye\GraphQLBundle\Generator\TypeGenerator;
use Symfony\Component\ExpressionLanguage\ExpressionFunction as BaseExpressionFunction;

class ExpressionFunction extends BaseExpressionFunction
{
    protected string $gqlServices = '$'.TypeGenerator::GRAPHQL_SERVICES;

    public function __construct(string $name, callable $compiler, ?callable $evaluator = null)
    {
        if (null === $evaluator) {
            $evaluator = new EvaluatorIsNotAllowedException($name);
        }

        parent::__construct($name, $compiler, $evaluator);
    }
}
