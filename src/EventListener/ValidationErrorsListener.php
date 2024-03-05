<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\EventListener;

use Redeye\GraphQLBundle\Error\InvalidArgumentsError;
use Redeye\GraphQLBundle\Event\ErrorFormattingEvent;

final class ValidationErrorsListener
{
    public function onErrorFormatting(ErrorFormattingEvent $event): void
    {
        $previous = $event->getError()->getPrevious();

        if ($previous && $previous instanceof InvalidArgumentsError) {
            $formattedError = $event->getFormattedError();
            $formattedError['extensions']['state'] = $previous->toState();
        }
    }
}
