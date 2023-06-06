<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser;

use Redeye\GraphQLBundle\Config\Parser\AttributeParser;

/**
 * @group legacy
 * @requires PHP 8.
 */
class AttributeParserTest extends MetadataParserTest
{
    public function parser(string $method, ...$args)
    {
        return AttributeParser::$method(...$args);
    }

    public function formatMetadata(string $metadata): string
    {
        return sprintf('#\[%s\]', $metadata);
    }
}
