<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\ExpressionLanguage\ExpressionFunction\Security;

use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\Security\HasRole;
use Redeye\GraphQLBundle\Generator\TypeGenerator;
use Redeye\GraphQLBundle\Tests\ExpressionLanguage\TestCase;

class HasRoleTest extends TestCase
{
    protected function getFunctions()
    {
        return [new HasRole()];
    }

    public function testEvaluator(): void
    {
        $security = $this->getSecurityIsGrantedWithExpectation(
            'ROLE_USER',
            $this->any()
        );
        $services = $this->createGraphQLServices(['security' => $security]);

        $hasRole = $this->expressionLanguage->evaluate('hasRole("ROLE_USER")', [TypeGenerator::GRAPHQL_SERVICES => $services]);
        $this->assertTrue($hasRole);
    }

    public function testHasRole(): void
    {
        $this->assertExpressionCompile('hasRole("ROLE_USER")', 'ROLE_USER');
    }
}
