<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Relay\Connection\fixtures;

use Redeye\GraphQLBundle\Relay\Connection\Output\Edge;

class CustomEdge extends Edge
{
    public string $customProperty;
}
