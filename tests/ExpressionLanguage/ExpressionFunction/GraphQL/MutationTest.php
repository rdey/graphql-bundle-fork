<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\GraphQL;

use Redeye\GraphQLBundle\ExpressionLanguage\Exception\EvaluatorIsNotAllowedException;
use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL\Mutation;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class MutationTest extends TestCase
{
    protected function getFunctions()
    {
        return [new Mutation(), new Mutation('mut')];
    }

    public function testEvaluatorThrowsException(): void
    {
        $this->expectException(EvaluatorIsNotAllowedException::class);
        $this->expressionLanguage->evaluate('mutation()');
    }

    public function testEvaluatorThrowsExceptionByAlias(): void
    {
        $this->expectException(EvaluatorIsNotAllowedException::class);
        $this->expressionLanguage->evaluate('mut()');
    }
}
