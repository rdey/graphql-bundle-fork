<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Definition\Builder;

use GraphQL\Type\Definition\Type;
use Redeye\GraphQLBundle\Definition\ConfigProcessor;
use Redeye\GraphQLBundle\Definition\GraphQLServices;

final class TypeFactory
{
    private ConfigProcessor $configProcessor;
    private GraphQLServices $graphQLServices;

    public function __construct(ConfigProcessor $configProcessor, GraphQLServices $graphQLServices)
    {
        $this->configProcessor = $configProcessor;
        $this->graphQLServices = $graphQLServices;
    }

    public function create(string $class): Type
    {
        return new $class($this->configProcessor, $this->graphQLServices);
    }
}
