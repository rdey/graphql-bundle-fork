<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Repository;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Provider(targetQueryTypes={"RootQuery2"}, targetMutationTypes="RootMutation2")
 */
#[GQL\Provider(targetQueryTypes: ['RootQuery2'], targetMutationTypes: 'RootMutation2')]
class WeaponRepository
{
    /**
     * @GQL\Query
     */
    #[GQL\Query]
    public function hasSecretWeapons(): bool
    {
        return true;
    }

    /**
     * @GQL\Query(targetTypes="RootQuery")
     */
    #[GQL\Query(targetTypes: 'RootQuery')]
    public function countSecretWeapons(): int
    {
        return 2;
    }

    /**
     * @GQL\Mutation
     */
    #[GQL\Mutation]
    public function createLightsaber(): bool
    {
        return true;
    }
}
