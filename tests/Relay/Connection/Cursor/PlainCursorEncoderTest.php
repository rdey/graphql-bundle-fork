<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Relay\Connection\Cursor;

use Redeye\GraphQLBundle\Relay\Connection\Cursor\PlainCursorEncoder;
use PHPUnit\Framework\TestCase;

final class PlainCursorEncoderTest extends TestCase
{
    /**
     * @var PlainCursorEncoder
     */
    private $encoder;

    protected function setUp(): void
    {
        $this->encoder = new PlainCursorEncoder();
    }

    public function testEncode(): void
    {
        $this->assertSame('foo', $this->encoder->encode('foo'));
    }

    public function testDecode(): void
    {
        $this->assertSame('foo', $this->encoder->decode('foo'));
    }
}
