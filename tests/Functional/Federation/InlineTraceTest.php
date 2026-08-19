<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\Federation;

use Redeye\GraphQLBundle\EventListener\InlineTraceListener;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace\Node;
use Redeye\GraphQLBundle\Federation\Tracing\TraceErrorFilter;
use Redeye\GraphQLBundle\Tests\Functional\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class InlineTraceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function ftv1Request(KernelBrowser $client, string $query, ?string $header = InlineTraceListener::HEADER_VALUE): array
    {
        $server = null === $header
            ? []
            : ['HTTP_APOLLO_FEDERATION_INCLUDE_TRACE' => $header];

        $client->request('GET', '/', ['query' => $query], [], $server);

        return json_decode((string) $client->getResponse()->getContent(), true);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function assertFtv1(array $response): Trace
    {
        $this->assertArrayHasKey('extensions', $response);
        $this->assertArrayHasKey('ftv1', $response['extensions']);

        $trace = new Trace();
        $trace->mergeFromString((string) base64_decode($response['extensions']['ftv1'], true));

        return $trace;
    }

    private function childNamed(Node $node, string $responseName): Node
    {
        foreach ($node->getChild() as $child) {
            if ($responseName === $child->getResponseName()) {
                return $child;
            }
        }

        self::fail(sprintf('no child named "%s"', $responseName));
    }

    public function testNoTraceWithoutTheHeader(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $response = $this->ftv1Request($client, '{ me { name } }', null);

        $this->assertSame(['me' => ['name' => 'Ada']], $response['data']);
        $this->assertArrayNotHasKey('ftv1', $response['extensions'] ?? []);
    }

    /**
     * @dataProvider nonMatchingHeaderProvider
     */
    public function testNoTraceForAnotherHeaderValue(string $header): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $response = $this->ftv1Request($client, '{ me { name } }', $header);

        $this->assertArrayNotHasKey('ftv1', $response['extensions'] ?? []);
    }

    public function nonMatchingHeaderProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'v1' => ['v1'];
        yield 'ftv2' => ['ftv2'];
    }

    public function testTraceIsAbsentWhenTheFeatureIsOff(): void
    {
        $client = static::createClient(['test_case' => 'connection']);

        $response = $this->ftv1Request($client, '{ user { name } }');

        $this->assertArrayNotHasKey('ftv1', $response['extensions'] ?? []);
    }

    public function testEmitsATraceWhoseRootMirrorsTheSelectionSet(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $trace = $this->assertFtv1($this->ftv1Request($client, '{ me { name } }'));

        $this->assertGreaterThan(0, (int) $trace->getDurationNs());
        $this->assertGreaterThan(0, (int) $trace->getStartTime()->getSeconds());
        $this->assertGreaterThan(0, (int) $trace->getEndTime()->getSeconds());
        $this->assertSame(1.0, $trace->getFieldExecutionWeight());

        $me = $this->childNamed($trace->getRoot(), 'me');
        $this->assertSame('User!', $me->getType());
        $this->assertSame('Query', $me->getParentType());

        $name = $this->childNamed($me, 'name');
        $this->assertSame('String', $name->getType());
        $this->assertSame('User', $name->getParentType());
    }

    public function testListItemsBecomeIndexNodes(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $trace = $this->assertFtv1($this->ftv1Request($client, '{ users { name } }'));

        $users = $this->childNamed($trace->getRoot(), 'users');
        $indices = [];
        foreach ($users->getChild() as $child) {
            $indices[] = $child->getIndex();
            $this->assertSame('index', $child->getId());
            $this->assertSame('name', $this->childNamed($child, 'name')->getResponseName());
        }

        $this->assertSame([0, 1], $indices);
    }

    public function testAliasedFieldCarriesTheSchemaFieldName(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $trace = $this->assertFtv1($this->ftv1Request($client, '{ currentUser: me { name } }'));

        $node = $this->childNamed($trace->getRoot(), 'currentUser');
        $this->assertSame('me', $node->getOriginalFieldName());
    }

    public function testDeferredFieldIsTimedWhenItSettles(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $trace = $this->assertFtv1($this->ftv1Request($client, '{ me { slowName } }'));

        $slowName = $this->childNamed($this->childNamed($trace->getRoot(), 'me'), 'slowName');
        $this->assertGreaterThan(
            2_000_000,
            (int) $slowName->getEndTime() - (int) $slowName->getStartTime(),
            'a deferred field was timed synchronously instead of on settle'
        );
    }

    public function testErrorIsMaskedAndAttachedToItsNode(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $response = $this->ftv1Request($client, '{ me { broken } }');
        $trace = $this->assertFtv1($response);

        $broken = $this->childNamed($this->childNamed($trace->getRoot(), 'me'), 'broken');
        $this->assertCount(1, $broken->getError());

        $error = $broken->getError()[0];
        $this->assertSame(TraceErrorFilter::MASKED_MESSAGE, $error->getMessage());
        $this->assertStringNotContainsString('a secret internal detail', $error->getJson());
        $this->assertSame(['me', 'broken'], json_decode($error->getJson(), true)['path']);
        $this->assertSame(TraceErrorFilter::MASKED_BY, json_decode($error->getJson(), true)['extensions']['maskedBy']);
    }

    /**
     * `unmodified` must report the message the client sees, never the raw exception text.
     */
    public function testUnmodifiedReportsTheClientVisibleMessage(): void
    {
        $client = static::createClient(['test_case' => 'inlineTraceUnmodified']);

        $response = $this->ftv1Request($client, '{ me { broken } }');
        $trace = $this->assertFtv1($response);

        $broken = $this->childNamed($this->childNamed($trace->getRoot(), 'me'), 'broken');
        $error = $broken->getError()[0];

        $this->assertSame($response['errors'][0]['message'], $error->getMessage());
        $this->assertStringNotContainsString('a secret internal detail', $error->getJson());
    }

    /**
     * A query that never parses resolves no fields at all, but must still produce a trace.
     */
    public function testEmitsATraceForAnUnparseableQuery(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $response = $this->ftv1Request($client, '{ me {');

        $this->assertArrayHasKey('errors', $response);
        $trace = $this->assertFtv1($response);

        $this->assertCount(0, $trace->getRoot()->getChild());
        $this->assertCount(1, $trace->getRoot()->getError());
        // Parse errors are masked like any other under the default policy.
        $this->assertSame(TraceErrorFilter::MASKED_MESSAGE, $trace->getRoot()->getError()[0]->getMessage());
    }

    public function testSyntaxErrorDetailReachesTheTraceWhenErrorsAreUnmodified(): void
    {
        $client = static::createClient(['test_case' => 'inlineTraceUnmodified']);

        $trace = $this->assertFtv1($this->ftv1Request($client, '{ me {'));

        $this->assertCount(1, $trace->getRoot()->getError());
        $this->assertStringContainsString('Syntax Error', $trace->getRoot()->getError()[0]->getMessage());
    }

    public function testEmitsATraceForAQueryThatFailsValidation(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $response = $this->ftv1Request($client, '{ me { noSuchField } }');

        $this->assertArrayHasKey('errors', $response);
        $trace = $this->assertFtv1($response);

        $this->assertCount(0, $trace->getRoot()->getChild());
        $this->assertCount(1, $trace->getRoot()->getError());
    }

    /**
     * The trace lives in the per-execution context rather than on the listener service, so each
     * query in a batch must get its own.
     */
    public function testEachQueryInABatchGetsItsOwnTrace(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $content = (string) json_encode([
            ['id' => 'ok', 'query' => '{ me { name } }'],
            ['id' => 'broken', 'query' => '{ me { broken } }'],
        ]);
        $client->request('POST', '/batch', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_APOLLO_FEDERATION_INCLUDE_TRACE' => InlineTraceListener::HEADER_VALUE,
        ], $content);

        $payloads = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertCount(2, $payloads);

        $first = $this->assertFtv1($payloads[0]['payload']);
        $second = $this->assertFtv1($payloads[1]['payload']);

        $this->assertNotSame(
            $payloads[0]['payload']['extensions']['ftv1'],
            $payloads[1]['payload']['extensions']['ftv1']
        );

        $this->assertSame('name', $this->childNamed($this->childNamed($first->getRoot(), 'me'), 'name')->getResponseName());
        $this->assertCount(0, $this->childNamed($first->getRoot(), 'me')->getError());

        $broken = $this->childNamed($this->childNamed($second->getRoot(), 'me'), 'broken');
        $this->assertCount(1, $broken->getError());
    }

    public function testTraceCoexistsWithOtherExtensions(): void
    {
        $client = static::createClient(['test_case' => 'inlineTrace']);

        $trace = $this->assertFtv1($this->ftv1Request($client, '{ me { name } }'));

        $this->assertNotNull($trace->getRoot());
    }
}
