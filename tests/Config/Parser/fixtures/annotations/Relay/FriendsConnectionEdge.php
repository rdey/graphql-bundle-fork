<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Relay;

use Redeye\GraphQLBundle\Annotation as GQL;
use Redeye\GraphQLBundle\Relay\Connection\Output\Edge;

/**
 * @GQL\Relay\Edge(node="Character")
 */
#[GQL\Relay\Edge(node: 'Character')]
class FriendsConnectionEdge extends Edge
{
}
