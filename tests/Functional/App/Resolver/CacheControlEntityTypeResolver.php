<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\Resolver;

use GraphQL\Type\Definition\ResolveInfo;
use Redeye\GraphQLBundle\Federation\EntityTypeResolver\EntityTypeResolverInterface;

class CacheControlEntityTypeResolver implements EntityTypeResolverInterface
{
    /**
     * @param mixed $value
     * @param mixed $context
     */
    public function __invoke($value, $context, ResolveInfo $info): ?string
    {
        return isset($value['title']) ? 'Post' : null;
    }
}
