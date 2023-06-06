<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\DependencyInjection\Builder;

use Redeye\GraphQLBundle\Definition\Builder\MappingInterface;

class RawIdField implements MappingInterface
{
    public function toMappingDefinition(array $config): array
    {
        return [
            'description' => 'The raw ID of an object',
            'type' => 'Int!',
            'resolve' => '@=value.id',
        ];
    }
}
