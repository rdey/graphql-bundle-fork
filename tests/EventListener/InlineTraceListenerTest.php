<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\EventListener;

use ArrayObject;
use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\Definition\Type\ExtensibleSchema;
use Redeye\GraphQLBundle\Event\ExecutorArgumentsEvent;
use Redeye\GraphQLBundle\Event\ExecutorResultEvent;
use Redeye\GraphQLBundle\EventListener\InlineTraceListener;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace;
use Redeye\GraphQLBundle\Federation\Tracing\TraceErrorFilter;
use Redeye\GraphQLBundle\Federation\Tracing\TraceErrorTransformerInterface;
use Redeye\GraphQLBundle\Federation\Tracing\TraceTreeBuilder;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class InlineTraceListenerTest extends TestCase
{
    private function listener(?Request $request, ?TraceErrorFilter $filter = null): InlineTraceListener
    {
        $stack = new RequestStack();
        if (null !== $request) {
            $stack->push($request);
        }

        return new InlineTraceListener(
            $stack,
            $filter ?? new TraceErrorFilter(TraceErrorFilter::MASKED, 'Internal server Error')
        );
    }

    private function requestWithHeader(?string $value): Request
    {
        $request = new Request();
        if (null !== $value) {
            $request->headers->set('apollo-federation-include-trace', $value);
        }

        return $request;
    }

    private function argumentsEvent(): ExecutorArgumentsEvent
    {
        $schema = new ExtensibleSchema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => ['me' => ['type' => Type::string()]],
            ]),
        ]);

        return ExecutorArgumentsEvent::create('default', $schema, '{ me }', new ArrayObject());
    }

    private function decode(ExecutionResult $result): Trace
    {
        $this->assertArrayHasKey('ftv1', $result->extensions);

        $trace = new Trace();
        $trace->mergeFromString((string) base64_decode($result->extensions['ftv1'], true));

        return $trace;
    }

    public function testStoresABuilderInTheContextWhenTheHeaderRequestsATrace(): void
    {
        $event = $this->argumentsEvent();

        $this->listener($this->requestWithHeader('ftv1'))->onPreExecutor($event);

        $this->assertInstanceOf(
            TraceTreeBuilder::class,
            $event->getContextValue()[InlineTraceListener::CONTEXT_KEY] ?? null
        );
    }

    public function testIgnoresARequestWithoutTheHeader(): void
    {
        $event = $this->argumentsEvent();

        $this->listener($this->requestWithHeader(null))->onPreExecutor($event);

        $this->assertArrayNotHasKey(InlineTraceListener::CONTEXT_KEY, $event->getContextValue());
    }

    /**
     * @dataProvider nonMatchingHeaderProvider
     */
    public function testIgnoresAHeaderWithAnotherValue(string $value): void
    {
        $event = $this->argumentsEvent();

        $this->listener($this->requestWithHeader($value))->onPreExecutor($event);

        $this->assertArrayNotHasKey(InlineTraceListener::CONTEXT_KEY, $event->getContextValue());
    }

    public function nonMatchingHeaderProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'v1' => ['v1'];
        yield 'ftv2' => ['ftv2'];
        yield 'padded' => [' ftv1'];
    }

    /**
     * RequestStack is empty under bin/console and in in-process test helpers.
     */
    public function testIgnoresExecutionWithNoCurrentRequest(): void
    {
        $event = $this->argumentsEvent();

        $this->listener(null)->onPreExecutor($event);

        $this->assertArrayNotHasKey(InlineTraceListener::CONTEXT_KEY, $event->getContextValue());
    }

    public function testAddsNoExtensionWhenNoTraceWasStarted(): void
    {
        $arguments = $this->argumentsEvent();
        $listener = $this->listener($this->requestWithHeader(null));
        $listener->onPreExecutor($arguments);

        $result = new ExecutionResult(['me' => 'Ada']);
        $listener->onPostExecutor(new ExecutorResultEvent($result, $arguments));

        $this->assertSame([], $result->extensions);
    }

    public function testEmitsABase64EncodedTraceOnTheResponse(): void
    {
        $arguments = $this->argumentsEvent();
        $listener = $this->listener($this->requestWithHeader('ftv1'));
        $listener->onPreExecutor($arguments);

        $result = new ExecutionResult(['me' => 'Ada']);
        $listener->onPostExecutor(new ExecutorResultEvent($result, $arguments));

        $trace = $this->decode($result);
        $this->assertGreaterThan(0, (int) $trace->getDurationNs());
        $this->assertNotNull($trace->getStartTime());
        $this->assertNotNull($trace->getEndTime());
        $this->assertSame(1.0, $trace->getFieldExecutionWeight());
    }

    public function testAttachesAPathedErrorToItsNode(): void
    {
        $arguments = $this->argumentsEvent();
        $listener = $this->listener($this->requestWithHeader('ftv1'));
        $listener->onPreExecutor($arguments);

        $result = new ExecutionResult(null, [
            new Error('boom', null, new Source('{ me }'), [3], ['me'], new RuntimeException('boom')),
        ]);
        $listener->onPostExecutor(new ExecutorResultEvent($result, $arguments));

        $root = $this->decode($result)->getRoot();
        $this->assertCount(0, $root->getError());

        $me = $root->getChild()[0];
        $this->assertSame('me', $me->getResponseName());
        $this->assertSame('<masked>', $me->getError()[0]->getMessage());
    }

    /**
     * A syntax or validation failure never resolves a field, but must still produce a trace.
     */
    public function testEmitsATraceForAnOperationThatNeverExecuted(): void
    {
        $arguments = $this->argumentsEvent();
        $listener = $this->listener($this->requestWithHeader('ftv1'));
        $listener->onPreExecutor($arguments);

        $result = new ExecutionResult(null, [new Error('Syntax Error: Unexpected <EOF>')]);
        $listener->onPostExecutor(new ExecutorResultEvent($result, $arguments));

        $root = $this->decode($result)->getRoot();
        $this->assertCount(1, $root->getError());
        $this->assertCount(0, $root->getChild());
    }

    public function testOmitsErrorsTheFilterDrops(): void
    {
        $dropEverything = new class() implements TraceErrorTransformerInterface {
            public function transform(Error $error): ?Error
            {
                return null;
            }
        };
        $filter = new TraceErrorFilter(TraceErrorFilter::MASKED, 'Internal server Error', $dropEverything);

        $arguments = $this->argumentsEvent();
        $listener = $this->listener($this->requestWithHeader('ftv1'), $filter);
        $listener->onPreExecutor($arguments);

        $result = new ExecutionResult(null, [new Error('boom')]);
        $listener->onPostExecutor(new ExecutorResultEvent($result, $arguments));

        $this->assertCount(0, $this->decode($result)->getRoot()->getError());
    }

    /**
     * Batched requests share one listener service but get one context each, so their traces must
     * not bleed into one another.
     */
    public function testEachExecutionGetsItsOwnTrace(): void
    {
        $listener = $this->listener($this->requestWithHeader('ftv1'));

        $first = $this->argumentsEvent();
        $listener->onPreExecutor($first);
        $second = $this->argumentsEvent();
        $listener->onPreExecutor($second);

        $this->assertNotSame(
            $first->getContextValue()[InlineTraceListener::CONTEXT_KEY],
            $second->getContextValue()[InlineTraceListener::CONTEXT_KEY]
        );

        $firstResult = new ExecutionResult(null, [new Error('only in the first')]);
        $listener->onPostExecutor(new ExecutorResultEvent($firstResult, $first));
        $secondResult = new ExecutionResult(['me' => 'Ada']);
        $listener->onPostExecutor(new ExecutorResultEvent($secondResult, $second));

        $this->assertCount(1, $this->decode($firstResult)->getRoot()->getError());
        $this->assertCount(0, $this->decode($secondResult)->getRoot()->getError());
    }

    public function testLeavesExistingExtensionsAlone(): void
    {
        $arguments = $this->argumentsEvent();
        $listener = $this->listener($this->requestWithHeader('ftv1'));
        $listener->onPreExecutor($arguments);

        $result = new ExecutionResult(['me' => 'Ada']);
        $result->extensions['debug'] = ['executionTime' => '1 ms'];
        $listener->onPostExecutor(new ExecutorResultEvent($result, $arguments));

        $this->assertSame(['executionTime' => '1 ms'], $result->extensions['debug']);
        $this->assertArrayHasKey('ftv1', $result->extensions);
    }
}
