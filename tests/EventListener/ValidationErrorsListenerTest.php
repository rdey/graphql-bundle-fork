<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\EventListener;

use GraphQL\Error\Error;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\Error\InvalidArgumentError;
use Redeye\GraphQLBundle\Error\InvalidArgumentsError;
use Redeye\GraphQLBundle\Event\ErrorFormattingEvent;
use Redeye\GraphQLBundle\EventListener\ValidationErrorsListener;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use function class_exists;

class ValidationErrorsListenerTest extends TestCase
{
    /** @var ValidationErrorsListener */
    private $listener;

    protected function setUp(): void
    {
        if (!class_exists(Validation::class)) {
            $this->markTestSkipped('Symfony validator component is not installed');
        }
        $this->listener = new ValidationErrorsListener();
    }

    public function testOnErrorFormatting(): void
    {
        $invalidArguments = new InvalidArgumentsError([new InvalidArgumentError('invalid', new ConstraintViolationList([new ConstraintViolation('message', 'error_template', [], '', 'prop1', 'invalid')]))]);
        $formattedError = [];
        $event = new ErrorFormattingEvent(Error::createLocatedError($invalidArguments), $formattedError);
        $this->listener->onErrorFormatting($event);

        $this->assertEquals(['extensions' => ['state' => ['invalid' => [0 => ['path' => 'prop1', 'message' => 'message', 'code' => null]]]]], $event->getFormattedError()->getArrayCopy());
    }
}
