<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\App\Mutation;

use Exception;
use Redeye\GraphQLBundle\Definition\Argument;
use Redeye\GraphQLBundle\Definition\Resolver\MutationInterface;
use Redeye\GraphQLBundle\Error\ResolveErrors;
use Redeye\GraphQLBundle\Validator\Exception\ArgumentsValidationException;
use Redeye\GraphQLBundle\Validator\InputValidator;

class InputValidatorMutation implements MutationInterface
{
    /**
     * @throws ArgumentsValidationException
     */
    public function mutationMock(Argument $args, ?InputValidator $validator = null): bool
    {
        if (null !== $validator) {
            $validator($args['groups']);
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function mutationErrors(ResolveErrors $errors): bool
    {
        $violations = $errors->getValidationErrors();

        if ($violations && 1 == $violations->count()) {
            return true;
        } elseif ($violations && 0 === $violations->count()) {
            return false;
        }

        throw new Exception("The injected variable `errors` doesn't contain an expected amount of violations.");
    }

    public function noValidation(): bool
    {
        return true;
    }
}
