<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Union;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Union(name="ResultSearch", types={"Hero", "Droid", "Sith"}, resolveType="value.getType()")
 * @GQL\Description("A search result")
 */
#[GQL\Union('ResultSearch', types: ['Hero', 'Droid', 'Sith'], resolveType: 'value.getType()')]
#[GQL\Description('A search result')]
class SearchResult
{
}
