<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Generator;

use Murtukov\PHPCodeGenerator\Collection as BaseCollection;
use Redeye\GraphQLBundle\Generator\Converter\ExpressionConverter;

/**
 * Extends the default Collection to properly convert expressions.
 */
class Collection extends BaseCollection
{
    /**
     * Mark converters to be used by convertion of array values.
     */
    protected array $converters = [ExpressionConverter::class];
}
