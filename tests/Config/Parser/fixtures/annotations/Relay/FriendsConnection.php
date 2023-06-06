<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Relay;

use Redeye\GraphQLBundle\Annotation as GQL;
use Redeye\GraphQLBundle\Relay\Connection\Output\Connection;

/**
 * @GQL\Relay\Connection(edge="FriendsConnectionEdge")
 */
#[GQL\Relay\Connection(edge: 'FriendsConnectionEdge')]
class FriendsConnection extends Connection
{
}
