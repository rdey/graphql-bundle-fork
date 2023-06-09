<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Relay\Connection;

use Redeye\GraphQLBundle\Definition\Builder\MappingInterface;

final class ConnectionArgsDefinition implements MappingInterface
{
    public function toMappingDefinition(array $config): array
    {
        return [
            'after' => [
                'type' => 'String',
            ],
            'first' => [
                'type' => 'Int',
            ],
            'before' => [
                'type' => 'String',
            ],
            'last' => [
                'type' => 'Int',
            ],
        ];
    }
}
