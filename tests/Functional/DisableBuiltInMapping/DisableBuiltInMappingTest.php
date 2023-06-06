<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\DisableBuiltInMapping;

use GraphQL\Type\Definition\Type;
use Redeye\GraphQLBundle\Resolver\UnresolvableException;
use Redeye\GraphQLBundle\Tests\Functional\TestCase;

class DisableBuiltInMappingTest extends TestCase
{
    protected function setUp(): void
    {
        static::bootKernel(['test_case' => 'disableBuiltInMapping']);
    }

    public function testPageInfoMustNotBePresent(): void
    {
        $this->expectException(UnresolvableException::class);
        $this->expectExceptionMessage('Could not find type with alias "PageInfo". Did you forget to define it?');

        $this->getType('PageInfo');
    }

    private function getType(string $type): ?Type
    {
        // @phpstan-ignore-next-line
        return $this->getContainer()->get('redeye_graphql.type_resolver')->resolve($type);
    }
}
