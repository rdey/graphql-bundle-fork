<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config\Parser\GraphQL\ASTConverter;

class InputObjectNode extends ObjectNode
{
    protected const TYPENAME = 'input-object';
}
