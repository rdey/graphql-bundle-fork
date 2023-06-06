<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DependencyInjection\Compiler;

class MutationTaggedServiceMappingTaggedPass extends QueryTaggedServiceMappingPass
{
    protected function getTagName(): string
    {
        return 'redeye_graphql.mutation';
    }

    protected function getResolverServiceID(): string
    {
        return 'redeye_graphql.mutation_resolver';
    }
}
