<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Federation\Tracing;

use GraphQL\Error\Error;
use GraphQL\Error\UserError;
use GraphQL\Language\Source;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\Federation\Tracing\TraceErrorFilter;
use Redeye\GraphQLBundle\Federation\Tracing\TraceErrorTransformerInterface;
use RuntimeException;

final class TraceErrorFilterTest extends TestCase
{
    private const INTERNAL_MESSAGE = 'Internal server Error';

    private function source(): Source
    {
        return new Source("{\n  me {\n    name\n  }\n}");
    }

    /**
     * An error the way execution produces one: located in the document, with a path, wrapping a
     * non-client-safe internal exception.
     */
    private function internalError(string $rawMessage = 'SQLSTATE[42S02]: no such table: users'): Error
    {
        return new Error(
            $rawMessage,
            null,
            $this->source(),
            [13],
            ['me', 'name'],
            new RuntimeException($rawMessage)
        );
    }

    private function clientSafeError(string $message = 'You may not do that'): Error
    {
        return new Error(
            $message,
            null,
            $this->source(),
            [13],
            ['me', 'name'],
            new UserError($message)
        );
    }

    public function testMaskedReplacesTheMessage(): void
    {
        $filter = new TraceErrorFilter(TraceErrorFilter::MASKED, self::INTERNAL_MESSAGE);

        $traced = $filter->filter($this->internalError());

        $this->assertNotNull($traced);
        $this->assertSame('<masked>', $traced->message);
    }

    public function testMaskedPreservesLocations(): void
    {
        $filter = new TraceErrorFilter(TraceErrorFilter::MASKED, self::INTERNAL_MESSAGE);

        $traced = $filter->filter($this->internalError());

        $this->assertNotNull($traced);
        $this->assertSame([['line' => 3, 'column' => 5]], $traced->locations);
    }

    public function testMaskedJsonCarriesMaskedByAndPathButNotTheRawMessage(): void
    {
        $filter = new TraceErrorFilter(TraceErrorFilter::MASKED, self::INTERNAL_MESSAGE);

        $traced = $filter->filter($this->internalError());

        $this->assertNotNull($traced);
        $decoded = json_decode($traced->json, true);

        $this->assertSame('<masked>', $decoded['message']);
        $this->assertSame(['me', 'name'], $decoded['path']);
        $this->assertSame(TraceErrorFilter::MASKED_BY, $decoded['extensions']['maskedBy']);
        $this->assertStringNotContainsString('SQLSTATE', $traced->json);
    }

    public function testMaskedDropsOriginalExtensions(): void
    {
        $error = new Error('boom', null, $this->source(), [13], ['me'], null, ['secret' => 'do-not-leak']);

        $filter = new TraceErrorFilter(TraceErrorFilter::MASKED, self::INTERNAL_MESSAGE);
        $traced = $filter->filter($error);

        $this->assertNotNull($traced);
        $this->assertStringNotContainsString('do-not-leak', $traced->json);
    }

    /**
     * The bundle's ErrorHandler masks at response-format time, not on the Error object, so the
     * raw exception text is still present while the trace is being built. `unmodified` must
     * report what the client sees -- never the raw message.
     */
    public function testUnmodifiedReportsTheClientVisibleMessageNotTheRawException(): void
    {
        $filter = new TraceErrorFilter(TraceErrorFilter::UNMODIFIED, self::INTERNAL_MESSAGE);

        $traced = $filter->filter($this->internalError());

        $this->assertNotNull($traced);
        $this->assertSame(self::INTERNAL_MESSAGE, $traced->message);
        $this->assertStringNotContainsString('SQLSTATE', $traced->json);
    }

    public function testUnmodifiedReportsTheRealMessageForClientSafeErrors(): void
    {
        $filter = new TraceErrorFilter(TraceErrorFilter::UNMODIFIED, self::INTERNAL_MESSAGE);

        $traced = $filter->filter($this->clientSafeError());

        $this->assertNotNull($traced);
        $this->assertSame('You may not do that', $traced->message);
    }

    public function testUnmodifiedPreservesLocationsAndPath(): void
    {
        $filter = new TraceErrorFilter(TraceErrorFilter::UNMODIFIED, self::INTERNAL_MESSAGE);

        $traced = $filter->filter($this->clientSafeError());

        $this->assertNotNull($traced);
        $this->assertSame([['line' => 3, 'column' => 5]], $traced->locations);
        $this->assertSame(['me', 'name'], json_decode($traced->json, true)['path']);
    }

    public function testTransformerReturningNullDropsTheError(): void
    {
        $filter = new TraceErrorFilter(
            TraceErrorFilter::MASKED,
            self::INTERNAL_MESSAGE,
            $this->transformer(static fn (Error $e): ?Error => null)
        );

        $this->assertNull($filter->filter($this->internalError()));
    }

    public function testTransformerOverridesTheIncludeErrorsMode(): void
    {
        $filter = new TraceErrorFilter(
            TraceErrorFilter::MASKED,
            self::INTERNAL_MESSAGE,
            $this->transformer(static fn (Error $e): ?Error => new Error('rewritten'))
        );

        $traced = $filter->filter($this->internalError());

        $this->assertNotNull($traced);
        $this->assertSame('rewritten', $traced->message, 'the transformer must win over `masked`');
    }

    /**
     * A transformer may only change the message and extensions. Locations and path always come
     * from the original error, so the error still lands on the right trace node.
     */
    public function testTransformerCannotMoveTheErrorToAnotherNode(): void
    {
        $filter = new TraceErrorFilter(
            TraceErrorFilter::MASKED,
            self::INTERNAL_MESSAGE,
            $this->transformer(static fn (Error $e): ?Error => new Error('rewritten', null, null, [], ['somewhere', 'else']))
        );

        $traced = $filter->filter($this->internalError());

        $this->assertNotNull($traced);
        $this->assertSame(['me', 'name'], json_decode($traced->json, true)['path']);
        $this->assertSame([['line' => 3, 'column' => 5]], $traced->locations);
    }

    public function testTransformerCanSetExtensions(): void
    {
        $filter = new TraceErrorFilter(
            TraceErrorFilter::MASKED,
            self::INTERNAL_MESSAGE,
            $this->transformer(static fn (Error $e): ?Error => new Error('rewritten', null, null, [], null, null, ['code' => 'E17']))
        );

        $traced = $filter->filter($this->internalError());

        $this->assertNotNull($traced);
        $this->assertSame('E17', json_decode($traced->json, true)['extensions']['code']);
    }

    public function testTransformerReceivesTheOriginalError(): void
    {
        $seen = null;
        $filter = new TraceErrorFilter(
            TraceErrorFilter::MASKED,
            self::INTERNAL_MESSAGE,
            $this->transformer(static function (Error $e) use (&$seen): ?Error {
                $seen = $e->getMessage();

                return $e;
            })
        );

        $filter->filter($this->internalError('the raw one'));

        $this->assertSame('the raw one', $seen);
    }

    public function testErrorWithoutLocationsProducesNoLocations(): void
    {
        $filter = new TraceErrorFilter(TraceErrorFilter::MASKED, self::INTERNAL_MESSAGE);

        $traced = $filter->filter(new Error('boom'));

        $this->assertNotNull($traced);
        $this->assertSame([], $traced->locations);
    }

    private function transformer(callable $fn): TraceErrorTransformerInterface
    {
        return new class($fn) implements TraceErrorTransformerInterface {
            /** @var callable */
            private $fn;

            public function __construct(callable $fn)
            {
                $this->fn = $fn;
            }

            public function transform(Error $error): ?Error
            {
                return ($this->fn)($error);
            }
        };
    }
}
