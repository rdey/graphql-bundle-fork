<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Annotation\Relay;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Redeye\GraphQLBundle\Annotation\Annotation;
use Redeye\GraphQLBundle\Annotation\Type;

/**
 * Annotation for GraphQL relay connection.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target("CLASS")
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Connection extends Type
{
    /**
     * Connection Edge type.
     */
    public ?string $edge;

    /**
     * Connection Node type.
     */
    public ?string $node;

    public function __construct(string $edge = null, string $node = null)
    {
        $this->edge = $edge;
        $this->node = $node;
    }
}
