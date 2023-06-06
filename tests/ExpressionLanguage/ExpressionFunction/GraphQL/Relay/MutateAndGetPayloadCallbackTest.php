<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\GraphQL\Relay;

use Redeye\GraphQLBundle\ExpressionLanguage\Exception\EvaluatorIsNotAllowedException;
use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL\Relay\MutateAndGetPayloadCallback;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class MutateAndGetPayloadCallbackTest extends TestCase
{
    protected function getFunctions()
    {
        return [new MutateAndGetPayloadCallback()];
    }

    public function testEvaluator(): void
    {
        $this->expectException(EvaluatorIsNotAllowedException::class);
        $this->expressionLanguage->evaluate('mutateAndGetPayloadCallback()');
    }
}
