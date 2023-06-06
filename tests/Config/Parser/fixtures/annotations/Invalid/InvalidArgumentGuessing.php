<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Invalid;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Type
 */
#[GQL\Type]
class InvalidArgumentGuessing
{
    /**
     * @GQL\Field(name="guessFailed")
     *
     * @param mixed $test
     */
    #[GQL\Field(name: 'guessFailed')]
    public function guessFail($test): int
    {
        return 12;
    }
}
