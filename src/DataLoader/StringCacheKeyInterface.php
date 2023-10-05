<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DataLoader;

interface StringCacheKeyInterface
{
    public function toCacheKey(): string;
}
