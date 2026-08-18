<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Federation\Tracing;

use Google\Protobuf\Timestamp;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace\Error as TraceError;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace\Location as TraceLocation;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace\Node as TraceNode;
use function ord;
use function sprintf;

/**
 * The generated protobuf classes must be wire-compatible with Apollo's `reports.proto`.
 *
 * Three independent layers, because each catches what the others cannot:
 *   1. round-trip           -- proves internal consistency only
 *   2. raw tag bytes        -- catches a transposed field number, which round-tripping cannot
 *   3. golden Apollo payload -- the only layer that proves interop
 */
final class TraceSerializationTest extends TestCase
{
    public function testRoundTripPreservesEveryFieldWeEmit(): void
    {
        $child = (new TraceNode())
            ->setResponseName('currentUser')
            ->setOriginalFieldName('me')
            ->setType('User!')
            ->setParentType('Query')
            ->setStartTime(1000)
            ->setEndTime(2000);

        $root = new TraceNode();
        $root->setChild([$child]);

        $trace = (new Trace())
            ->setStartTime(new Timestamp(['seconds' => 1660000000, 'nanos' => 123000000]))
            ->setEndTime(new Timestamp(['seconds' => 1660000001, 'nanos' => 456000000]))
            ->setDurationNs(1333000000)
            ->setFieldExecutionWeight(1.0)
            ->setRoot($root);

        $decoded = new Trace();
        $decoded->mergeFromString($trace->serializeToString());

        $this->assertSame(1660000000, (int) $decoded->getStartTime()->getSeconds());
        $this->assertSame(123000000, $decoded->getStartTime()->getNanos());
        $this->assertSame(1660000001, (int) $decoded->getEndTime()->getSeconds());
        $this->assertSame(456000000, $decoded->getEndTime()->getNanos());
        $this->assertSame(1333000000, (int) $decoded->getDurationNs());
        $this->assertSame(1.0, $decoded->getFieldExecutionWeight());

        $decodedChild = $decoded->getRoot()->getChild()[0];
        $this->assertSame('currentUser', $decodedChild->getResponseName());
        $this->assertSame('me', $decodedChild->getOriginalFieldName());
        $this->assertSame('User!', $decodedChild->getType());
        $this->assertSame('Query', $decodedChild->getParentType());
        $this->assertSame(1000, (int) $decodedChild->getStartTime());
        $this->assertSame(2000, (int) $decodedChild->getEndTime());
    }

    public function testIndexNodeRoundTrips(): void
    {
        $root = new TraceNode();
        $root->setChild([(new TraceNode())->setIndex(3)]);

        $decoded = new Trace();
        $decoded->mergeFromString((new Trace())->setRoot($root)->serializeToString());

        $node = $decoded->getRoot()->getChild()[0];
        $this->assertSame(3, $node->getIndex());
        $this->assertSame('index', $node->getId(), 'the `id` oneof should report `index` as the set field');
    }

    public function testErrorRoundTrips(): void
    {
        $error = (new TraceError())
            ->setMessage('<masked>')
            ->setJson('{"message":"<masked>"}');
        $error->setLocation([(new TraceLocation())->setLine(2)->setColumn(7)]);

        $root = new TraceNode();
        $root->setError([$error]);

        $decoded = new Trace();
        $decoded->mergeFromString((new Trace())->setRoot($root)->serializeToString());

        $decodedError = $decoded->getRoot()->getError()[0];
        $this->assertSame('<masked>', $decodedError->getMessage());
        $this->assertSame('{"message":"<masked>"}', $decodedError->getJson());
        $this->assertSame(2, $decodedError->getLocation()[0]->getLine());
        $this->assertSame(7, $decodedError->getLocation()[0]->getColumn());
    }

    /**
     * A round-trip cannot catch a transposed field number -- it would encode and decode with the
     * same wrong tag. Asserting the raw tag bytes pins the numbering to Apollo's reports.proto.
     *
     * Tag byte = (field_number << 3) | wire_type.
     *
     * @dataProvider tagByteProvider
     */
    public function testFieldNumbersMatchApolloReportsProto(string $description, Trace $trace, int $expectedTagByte): void
    {
        $bytes = $trace->serializeToString();

        $this->assertSame(
            $expectedTagByte,
            ord($bytes[0]),
            sprintf('%s: expected leading tag byte 0x%02X, got 0x%02X', $description, $expectedTagByte, ord($bytes[0]))
        );
    }

    public function tagByteProvider(): iterable
    {
        // duration_ns = field 11, varint (wire type 0) => (11 << 3) | 0 = 88 = 0x58
        yield 'Trace.duration_ns is field 11' => [
            'Trace.duration_ns',
            (new Trace())->setDurationNs(42),
            0x58,
        ];

        // root = field 14, length-delimited (wire type 2) => (14 << 3) | 2 = 114 = 0x72
        yield 'Trace.root is field 14' => [
            'Trace.root',
            (new Trace())->setRoot(new TraceNode()),
            0x72,
        ];

        // end_time = field 3, length-delimited => (3 << 3) | 2 = 26 = 0x1A
        yield 'Trace.end_time is field 3' => [
            'Trace.end_time',
            (new Trace())->setEndTime(new Timestamp(['seconds' => 1])),
            0x1A,
        ];

        // start_time = field 4, length-delimited => (4 << 3) | 2 = 34 = 0x22
        yield 'Trace.start_time is field 4' => [
            'Trace.start_time',
            (new Trace())->setStartTime(new Timestamp(['seconds' => 1])),
            0x22,
        ];

        // field_execution_weight = field 31, 64-bit (wire type 1) => (31 << 3) | 1 = 249 = 0xF9
        yield 'Trace.field_execution_weight is field 31' => [
            'Trace.field_execution_weight',
            (new Trace())->setFieldExecutionWeight(1.0),
            0xF9,
        ];
    }

    /**
     * @dataProvider nodeTagByteProvider
     */
    public function testNodeFieldNumbersMatchApolloReportsProto(string $description, TraceNode $node, int $expectedTagByte): void
    {
        $bytes = $node->serializeToString();

        $this->assertSame(
            $expectedTagByte,
            ord($bytes[0]),
            sprintf('%s: expected leading tag byte 0x%02X, got 0x%02X', $description, $expectedTagByte, ord($bytes[0]))
        );
    }

    public function nodeTagByteProvider(): iterable
    {
        // response_name = 1, length-delimited => (1 << 3) | 2 = 10 = 0x0A
        yield 'Node.response_name is field 1' => ['Node.response_name', (new TraceNode())->setResponseName('a'), 0x0A];
        // index = 2, varint => (2 << 3) | 0 = 16 = 0x10
        yield 'Node.index is field 2' => ['Node.index', (new TraceNode())->setIndex(1), 0x10];
        // type = 3, length-delimited => (3 << 3) | 2 = 26 = 0x1A
        yield 'Node.type is field 3' => ['Node.type', (new TraceNode())->setType('a'), 0x1A];
        // start_time = 8, varint => (8 << 3) | 0 = 64 = 0x40
        yield 'Node.start_time is field 8' => ['Node.start_time', (new TraceNode())->setStartTime(1), 0x40];
        // end_time = 9, varint => (9 << 3) | 0 = 72 = 0x48
        yield 'Node.end_time is field 9' => ['Node.end_time', (new TraceNode())->setEndTime(1), 0x48];
        // parent_type = 13, length-delimited => (13 << 3) | 2 = 106 = 0x6A
        yield 'Node.parent_type is field 13' => ['Node.parent_type', (new TraceNode())->setParentType('a'), 0x6A];
        // original_field_name = 14, length-delimited => (14 << 3) | 2 = 114 = 0x72
        yield 'Node.original_field_name is field 14' => ['Node.original_field_name', (new TraceNode())->setOriginalFieldName('a'), 0x72];
    }

    /**
     * The only test that proves interop: a real ftv1 payload emitted by Apollo Server, parsed
     * with *our* generated classes. If our field numbers or wire types diverge from upstream,
     * the decoded values come out wrong or missing.
     *
     * @see tests/Federation/Tracing/fixtures/README.md for how the fixture was produced.
     */
    public function testDecodesGoldenApolloPayload(): void
    {
        $base64 = trim((string) file_get_contents(__DIR__.'/fixtures/apollo-ftv1.b64'));

        $trace = new Trace();
        $trace->mergeFromString((string) base64_decode($base64, true));

        $this->assertGreaterThan(0, (int) $trace->getDurationNs(), 'duration_ns did not decode');
        $this->assertNotNull($trace->getStartTime(), 'start_time did not decode');
        $this->assertNotNull($trace->getEndTime(), 'end_time did not decode');
        $this->assertNotNull($trace->getRoot(), 'root did not decode');

        $children = iterator_to_array($trace->getRoot()->getChild());
        $this->assertCount(1, $children);

        $me = $children[0];
        $this->assertSame('me', $me->getResponseName());
        $this->assertSame('User!', $me->getType());
        $this->assertSame('Query', $me->getParentType());
        $this->assertGreaterThan(0, (int) $me->getEndTime());

        $name = iterator_to_array($me->getChild())[0];
        $this->assertSame('name', $name->getResponseName());
        $this->assertSame('String', $name->getType());
        $this->assertSame('User', $name->getParentType());
    }
}
