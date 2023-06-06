<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security\IsAuthenticated;
use Redeye\GraphQLBundle\Generator\TypeGenerator;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class IsAuthenticatedTest extends TestCase
{
    protected function getFunctions()
    {
        return [new IsAuthenticated()];
    }

    public function testEvaluator(): void
    {
        $security = $this->getSecurityIsGrantedWithExpectation(
            $this->matchesRegularExpression('/^IS_AUTHENTICATED_(REMEMBERED|FULLY)$/'),
            $this->any()
        );
        $gqlServices = $this->createGraphQLServices(['security' => $security]);

        $isAuthenticated = $this->expressionLanguage->evaluate(
            'isAuthenticated()',
            [TypeGenerator::GRAPHQL_SERVICES => $gqlServices]
        );
        $this->assertTrue($isAuthenticated);
    }

    public function testIsAuthenticated(): void
    {
        $this->assertExpressionCompile(
            'isAuthenticated()',
            $this->matchesRegularExpression('/^IS_AUTHENTICATED_(REMEMBERED|FULLY)$/')
        );
    }
}
