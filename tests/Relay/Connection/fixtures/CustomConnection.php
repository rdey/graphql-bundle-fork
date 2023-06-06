<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Relay\Connection\fixtures;

use Redeye\GraphQLBundle\Relay\Connection\Output\Connection;

class CustomConnection extends Connection
{
    public int $averageAge;
}
