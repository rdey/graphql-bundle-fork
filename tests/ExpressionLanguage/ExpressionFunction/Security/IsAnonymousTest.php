<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security\IsAnonymous;
use Redeye\GraphQLBundle\Generator\TypeGenerator;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class IsAnonymousTest extends TestCase
{
    protected function getFunctions()
    {
        return [new IsAnonymous()];
    }

    public function testEvaluator(): void
    {
        $security = $this->getSecurityIsGrantedWithExpectation(
            'IS_AUTHENTICATED_ANONYMOUSLY',
            $this->any()
        );
        $services = $this->createGraphQLServices(['security' => $security]);

        $isAnonymous = $this->expressionLanguage->evaluate('isAnonymous()', [TypeGenerator::GRAPHQL_SERVICES => $services]);
        $this->assertTrue($isAnonymous);
    }

    public function testIsAnonymous(): void
    {
        $this->assertExpressionCompile('isAnonymous()', 'IS_AUTHENTICATED_ANONYMOUSLY');
    }
}
