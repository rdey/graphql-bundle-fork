<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Relay;

use Redeye\GraphQLBundle\Annotation as GQL;
use Redeye\GraphQLBundle\Relay\Connection\Output\Connection;

/**
 * @GQL\Relay\Connection(node="Character")
 */
#[GQL\Relay\Connection(node: 'Character')]
class EnemiesConnection extends Connection
{
}
