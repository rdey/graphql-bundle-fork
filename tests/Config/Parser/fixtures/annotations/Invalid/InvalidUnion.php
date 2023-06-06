<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Invalid;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Union(types={"Hero", "Droid", "Sith"})
 */
#[GQL\Union(types: ['Hero', 'Droid', 'Sith'])]
class InvalidUnion
{
}
