<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\GraphQL;

use Redeye\GraphQLBundle\ExpressionLanguage\Exception\EvaluatorIsNotAllowedException;
use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL\Query;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class QueryTest extends TestCase
{
    protected function getFunctions()
    {
        return [new Query(), new Query('q')];
    }

    public function testEvaluatorThrowsException(): void
    {
        $this->expectException(EvaluatorIsNotAllowedException::class);
        $this->expressionLanguage->evaluate('query()');
    }

    public function testEvaluatorThrowsExceptionByAlias(): void
    {
        $this->expectException(EvaluatorIsNotAllowedException::class);
        $this->expressionLanguage->evaluate('q()');
    }
}
