<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\GraphQL\HelloWord\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Redeye\GraphQLBundle\Definition\Resolver\AliasedInterface;
use Redeye\GraphQLBundle\Resolver\MutationResolver;

final class MutationType extends ObjectType implements AliasedInterface
{
    public function __construct(MutationResolver $mutator)
    {
        parent::__construct([
            'name' => 'Calc',
            'fields' => [
                'sum' => [
                    'type' => Type::int(),
                    'args' => [
                        'x' => ['type' => Type::int()],
                        'y' => ['type' => Type::int()],
                    ],
                    'resolve' => function ($root, $args) use ($mutator) {
                        return $mutator->resolve([
                            'sum',
                            [$args['x'], $args['y']],
                        ]);
                    },
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getAliases(): array
    {
        return ['Calc'];
    }
}
