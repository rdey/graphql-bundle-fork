<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Annotation;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Annotation for GraphQL field builders.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY", "METHOD"})
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class FieldBuilder extends Builder
{
}
