<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Config\Parser\fixtures\annotations\Type;

use Redeye\GraphQLBundle\Annotation as GQL;

/**
 * @GQL\Type
 * @GQL\Description("The Cat type")
 */
#[GQL\Type]
#[GQL\Description('The Cat type')]
class Cat extends Animal
{
    /**
     * @GQL\Field(type="Int!")
     */
    #[GQL\Field(type: 'Int!')]
    protected int $lives;

    /**
     * @GQL\Field
     *
     * @var string[]
     */
    #[GQL\Field]
    protected array $toys;
}
