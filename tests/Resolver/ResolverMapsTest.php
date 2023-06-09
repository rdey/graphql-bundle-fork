<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Resolver;

use InvalidArgumentException;
use Redeye\GraphQLBundle\Resolver\ResolverMapInterface;
use Redeye\GraphQLBundle\Resolver\ResolverMaps;
use Redeye\GraphQLBundle\Resolver\UnresolvableException;
use PHPUnit\Framework\TestCase;
use stdClass;
use function sprintf;

class ResolverMapsTest extends TestCase
{
    public function testUnresolvable(): void
    {
        $resolverMaps = new ResolverMaps([]);
        $this->expectException(UnresolvableException::class);
        $this->expectExceptionMessage('Field "Foo.bar" could not be resolved.');
        $resolverMaps->resolve('Foo', 'bar');
    }

    /**
     * @dataProvider invalidResolverMapDataProvider
     *
     * @param string $type
     */
    public function testInvalidResolverMap(array $resolverMaps, $type): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'ResolverMap should be instance of "%s" but got "%s".',
            ResolverMapInterface::class,
            $type
        ));
        new ResolverMaps($resolverMaps);
    }

    public function invalidResolverMapDataProvider(): array
    {
        return [
            [[null], 'NULL'],
            [[false], 'boolean'],
            [[true], 'boolean'],
            [['baz'], 'string'],
            [[new stdClass()], 'stdClass'],
        ];
    }
}
