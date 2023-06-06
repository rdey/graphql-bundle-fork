<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Validator;

use Redeye\GraphQLBundle\Event\ErrorFormattingEvent;
use Redeye\GraphQLBundle\Validator\Exception\ArgumentsValidationException;

/**
 * Class Formatter.
 *
 * Adds validation errors to the response.
 *
 * @see https://github.com/redeye/GraphQLBundle/blob/master/docs/validation/index.md#error-messages
 */
class Formatter
{
    public function onErrorFormatting(ErrorFormattingEvent $event): void
    {
        $error = $event->getError()->getPrevious();

        if ($error instanceof ArgumentsValidationException) {
            $validation = [];

            foreach ($error->getViolations() as $violation) {
                $validation[$violation->getPropertyPath()][] = [
                    'message' => $violation->getMessage(),
                    'code' => $violation->getCode(),
                ];
            }

            $formattedError = $event->getFormattedError();
            $formattedError['extensions']['validation'] = $validation;
        }
    }
}
