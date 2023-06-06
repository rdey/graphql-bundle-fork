<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\DependencyInjection\Compiler;

use InvalidArgumentException;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\GraphQLServicesPass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GraphQLServicesPassTest extends TestCase
{
    /**
     * @param mixed $invalidAlias
     *
     * @dataProvider invalidAliasProvider
     */
    public function testInvalidAlias($invalidAlias): void
    {
        /** @var ContainerBuilder|MockObject $container */
        $container = $this->getMockBuilder(ContainerBuilder::class)
            ->onlyMethods(['findTaggedServiceIds', 'findDefinition'])
            ->getMock();

        // TODO: remove following code in 1.0
        // remove start
        $container->expects($this->exactly(2))
            ->method('findTaggedServiceIds')
            ->withConsecutive(['redeye_graphql.service'], ['redeye_graphql.global_variable'])
            ->willReturnOnConsecutiveCalls(
                [
                    'my-id' => [
                        ['alias' => $invalidAlias],
                    ],
                ],
                []
            );
        // remove end

        // TODO: uncomment following code in 1.0
//        $container = $this->getMockBuilder(ContainerBuilder::class)
//            ->onlyMethods(['findTaggedServiceIds', 'findDefinition'])
//            ->getMock();
//        $container->expects($this->once())
//            ->method('findTaggedServiceIds')
//            ->willReturn([
//                'my-id' => [
//                    ['alias' => $invalidAlias],
//                ],
//            ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "my-id" tagged "redeye_graphql.service" should have a valid "alias" attribute.');

        (new GraphQLServicesPass())->process($container);
    }

    public function invalidAliasProvider(): array
    {
        return [
            [null],
            [new stdClass()],
            [[]],
            [true],
            [false],
            [''],
        ];
    }
}
