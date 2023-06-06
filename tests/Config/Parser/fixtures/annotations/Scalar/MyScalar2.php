<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Scalar;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Scalar(name="MyScalar", scalarType="newObject('App\\Type\\EmailType')")
 */
#[GQL\Scalar(name: 'MyScalar', scalarType: "newObject('App\\Type\\EmailType')")]
class MyScalar2
{
}
