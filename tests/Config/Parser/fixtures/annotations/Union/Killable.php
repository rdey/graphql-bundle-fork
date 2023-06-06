<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Union;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Union(resolveType="value.getType()")
 */
#[GQL\Union(resolveType: 'value.getType()')]
interface Killable
{
}
