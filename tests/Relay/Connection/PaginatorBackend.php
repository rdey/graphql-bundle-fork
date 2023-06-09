<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Relay\Connection;

use function count;

/**
 * Class PaginatorBackend.
 */
class PaginatorBackend
{
    public function count(array $array): int
    {
        return count($array);
    }
}
