<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Invalid;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Type
 */
#[GQL\Type]
class InvalidAccess
{
    /**
     * @GQL\Access("access")
     */
    #[GQL\Access('access')]
    protected string $field;
}
