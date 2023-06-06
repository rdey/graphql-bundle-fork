<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Definition\ConfigProcessor;

interface ConfigProcessorInterface
{
    public function process(array $config): array;
}
