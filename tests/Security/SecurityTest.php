<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Security;

use LogicException;
use Redeye\GraphQLBundle\Security\Security;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    public function testIsGrantedSecurityCoreComponentRequired(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "symfony/security-core" component is required.');
        (new Security(null))->isGranted('ROLE_USER');
    }

    public function testGetUserSecurityCoreComponentRequired(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "symfony/security-core" component is required.');
        (new Security(null))->getUser();
    }
}
