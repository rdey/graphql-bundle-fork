<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Invalid;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Type
 */
#[GQL\Type]
class InvalidPrivateMethod
{
    /**
     * @GQL\Field
     */
    #[GQL\Field]
    protected function gql(): string
    {
        return 'invalid';
    }
}
