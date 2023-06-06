<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction\GraphQL\Relay;

use Murtukov\PHPCodeGenerator\Closure;
use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionFunction;
use Redeye\GraphQLBundle\Generator\TypeGenerator;

final class IdFetcherCallback extends ExpressionFunction
{
    public function __construct()
    {
        parent::__construct(
            'idFetcherCallback',
            static fn ($idFetcher) => (
                Closure::new()
                    ->addArgument('value')
                    ->bindVars(TypeGenerator::GRAPHQL_SERVICES, 'args', 'context', 'info')
                    ->append("return $idFetcher")
                    ->generate()
            )
        );
    }
}
