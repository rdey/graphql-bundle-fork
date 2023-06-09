<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Invalid;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Provider
 */
#[GQL\Provider]
class InvalidProvider
{
    /**
     * @GQL\Query(type="Int", targetType="RootMutation2")
     */
    #[GQL\Query(type: 'Int', targetType: 'RootMutation2')]
    public function noQueryOnMutation(): array
    {
        return [];
    }

    /**
     * @GQL\Mutation(type="Int", targetType="RootQuery2")
     */
    #[GQL\Mutation(type: 'Int', targetType: 'RootQuery2')]
    public function noMutationOnQuery(): array
    {
        return [];
    }
}
