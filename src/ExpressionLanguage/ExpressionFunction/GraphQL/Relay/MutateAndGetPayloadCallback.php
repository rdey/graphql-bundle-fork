<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL\Relay;

use Murtukov\PHPCodeGenerator\Closure;
use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class MutateAndGetPayloadCallback extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'mutateAndGetPayloadCallback',
            static fn ($mutateAndGetPayload) => (
                Closure::new()
                    ->addArgument('value')
                    ->bindVars(TypeGenerator::GRAPHQL_SERVICES, 'args', 'context', 'info')
                    ->append("return $mutateAndGetPayload")
                    ->generate()
            )
        );
    }
}
