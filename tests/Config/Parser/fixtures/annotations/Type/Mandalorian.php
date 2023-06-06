<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Type;

use Redeye\GraphQLBundle\Annotation as GQL;
use Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Union\Killable;

/**
 * @GQL\Type
 */
#[GQL\Type]
class Mandalorian extends Character implements Killable, Armored
{
}
