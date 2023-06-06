<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\GraphQL\Relay;

use Redeye\GraphQLBundle\ExpressionLanguage\Exception\EvaluatorIsNotAllowedException;
use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL\Relay\ResolveSingleInputCallback;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class ResolveSingleInputCallbackTest extends TestCase
{
    protected function getFunctions()
    {
        return [new ResolveSingleInputCallback()];
    }

    public function testEvaluator(): void
    {
        $this->expectException(EvaluatorIsNotAllowedException::class);
        $this->expressionLanguage->evaluate('resolveSingleInputCallback()');
    }
}
