<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DependencyInjection\Compiler;

class TypeTaggedServiceMappingPass extends TaggedServiceMappingPass
{
    public const TAG_NAME = 'redeye_graphql.type';

    protected function getTagName(): string
    {
        return self::TAG_NAME;
    }

    protected function getResolverServiceID(): string
    {
        return 'redeye_graphql.type_resolver';
    }
}
