<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\DependencyInjection\Builder;

use Redeye\GraphQLBundle\Definition\Builder\MappingInterface;

class PagerArgs implements MappingInterface
{
    public function toMappingDefinition(array $config): array
    {
        $defaultLimit = isset($config['defaultLimit']) ? (int) $config['defaultLimit'] : 20;

        return [
            'limit' => [
                'type' => 'Int!',
                'defaultValue' => $defaultLimit,
            ],
            'offset' => [
                'type' => 'Int!',
                'defaultValue' => 0,
            ],
        ];
    }
}
