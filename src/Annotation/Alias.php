<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Annotation for GraphQL args builders.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"METHOD"})
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Alias extends Annotation
{
    public string $alias;

    public function __construct(string $alias)
    {
        $this->alias = $alias;
    }
}
