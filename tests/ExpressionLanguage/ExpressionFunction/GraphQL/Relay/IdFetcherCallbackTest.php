<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\GraphQL\Relay;

use Redeye\GraphQLBundle\ExpressionLanguage\Exception\EvaluatorIsNotAllowedException;
use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL\Relay\IdFetcherCallback;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class IdFetcherCallbackTest extends TestCase
{
    protected function getFunctions()
    {
        return [new IdFetcherCallback()];
    }

    public function testEvaluator(): void
    {
        $this->expectException(EvaluatorIsNotAllowedException::class);
        $this->expressionLanguage->evaluate('idFetcherCallback()');
    }
}
