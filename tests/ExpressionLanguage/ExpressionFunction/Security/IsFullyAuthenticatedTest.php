<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security\IsFullyAuthenticated;
use Redeye\GraphQLBundle\Generator\TypeGenerator;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class IsFullyAuthenticatedTest extends TestCase
{
    protected function getFunctions()
    {
        return [new IsFullyAuthenticated()];
    }

    public function testEvaluator(): void
    {
        $security = $this->getSecurityIsGrantedWithExpectation(
            'IS_AUTHENTICATED_FULLY',
            $this->any()
        );
        $gqlServices = $this->createGraphQLServices(['security' => $security]);

        $isFullyAuthenticated = $this->expressionLanguage->evaluate(
            'isFullyAuthenticated()',
            [TypeGenerator::GRAPHQL_SERVICES => $gqlServices]
        );
        $this->assertTrue($isFullyAuthenticated);
    }

    public function testIsFullyAuthenticated(): void
    {
        $this->assertExpressionCompile('isFullyAuthenticated()', 'IS_AUTHENTICATED_FULLY');
    }
}
