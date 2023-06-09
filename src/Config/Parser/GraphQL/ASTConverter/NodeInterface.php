<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config\Parser\GraphQL\ASTConverter;

use GraphQL\Language\AST\Node;

interface NodeInterface
{
    public static function toConfig(Node $node): array;
}
