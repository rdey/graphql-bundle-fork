<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Type;

use Redeye\GraphQLBundle\Annotation as GQL;
use Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Enum\Race;
use Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Union\Killable;

/**
 * @GQL\Type(interfaces={"Character"})
 * @GQL\Description("The Hero type")
 */
#[GQL\Type(interfaces: ['Character'])]
#[GQL\Description('The Hero type')]
class Hero extends Character implements Killable
{
    /**
     * @GQL\Field(type="Race")
     */
    #[GQL\Field(type: 'Race')]
    protected Race $race;
}
