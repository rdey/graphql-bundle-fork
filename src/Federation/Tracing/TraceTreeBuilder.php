<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation\Tracing;

use Google\Protobuf\Timestamp;
use GraphQL\Type\Definition\ResolveInfo;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace\Error as ProtoError;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace\Location as ProtoLocation;
use Redeye\GraphQLBundle\Federation\Tracing\Proto\Trace\Node as ProtoNode;
use function array_slice;

/**
 * Builds the node tree for one FTV1 inline trace.
 *
 * Port of Apollo Server's `TraceTreeBuilder` combined with federation-jvm's `ProtoBuilderTree`,
 * which contributes the on-demand ancestor synthesis that graphql-js gets for free from its
 * linked-list ResponsePath.
 *
 * See docs/federation/inline-trace.md.
 *
 * @internal
 */
final class TraceTreeBuilder
{
    /**
     * A large list response can produce an unbounded number of nodes. Past this many, recording
     * stops: a truncated trace is preferable to exhausting memory. Apollo Server has no such cap,
     * but PHP's per-object overhead makes one worthwhile.
     */
    public const DEFAULT_MAX_NODES = 25000;

    private TraceNode $root;

    /**
     * Nodes by dot-joined response path. Seeded with the root under the empty key.
     *
     * Collisions are impossible: a GraphQL response name is a letter or underscore followed by
     * letters, digits or underscores, so it never contains a dot; list indices are integers.
     *
     * @var array<string, TraceNode>
     */
    private array $nodes;

    private int $nodeCount = 0;
    private int $maxNodes;

    private ?int $startHrTime = null;
    private ?float $startWallTime = null;
    private ?float $endWallTime = null;
    private int $durationNs = 0;
    private bool $stopped = false;

    public function __construct(int $maxNodes = self::DEFAULT_MAX_NODES)
    {
        $this->maxNodes = $maxNodes;
        $this->root = new TraceNode();
        $this->nodes = ['' => $this->root];
    }

    public function startTiming(): void
    {
        $this->startWallTime = microtime(true);
        $this->startHrTime = hrtime(true);
    }

    public function getRoot(): TraceNode
    {
        return $this->root;
    }

    /**
     * Records the start of a field resolution and returns the callback that records its end.
     *
     * The callback captures the node, never the ResolveInfo or the path: graphql-php reuses a
     * single ResolveInfo across list items and mutates its `path` in completeListValue(), so a
     * callback that re-derived the path when the field settles would write into the wrong node.
     */
    public function willResolveField(ResolveInfo $info): callable
    {
        if ($this->stopped || $this->nodeCount >= $this->maxNodes) {
            return static function (): void {
            };
        }

        $node = $this->ensureNode($info->path);
        $node->type = $info->returnType->toString();
        $node->parentType = $info->parentType->name;
        $node->startTime = $this->elapsedNs();

        $responseName = end($info->path);
        if (is_string($responseName) && $responseName !== $info->fieldName) {
            $node->originalFieldName = $info->fieldName;
        }

        return function () use ($node): void {
            $node->endTime = $this->elapsedNs();
        };
    }

    /**
     * @param array<int, string|int>|null $path null for parse, validation and other whole-operation
     *                                          errors, which belong on the root node
     */
    public function addError(?array $path, TraceError $error): void
    {
        $node = null === $path || [] === $path ? $this->root : $this->ensureNode($path);
        $node->errors[] = $error;
    }

    public function stopTiming(): void
    {
        if ($this->stopped) {
            return;
        }

        $this->durationNs = $this->elapsedNs();
        $this->endWallTime = microtime(true);
        $this->stopped = true;

        $this->backfillEndTimes($this->root);
    }

    public function toProto(): Trace
    {
        $trace = new Trace();
        $trace->setDurationNs($this->durationNs);
        // Apollo Server always sends 1; Studio scales field usage statistics by it.
        $trace->setFieldExecutionWeight(1.0);
        $trace->setRoot($this->toProtoNode($this->root));

        if (null !== $this->startWallTime) {
            $trace->setStartTime(self::toTimestamp($this->startWallTime));
        }

        if (null !== $this->endWallTime) {
            $trace->setEndTime(self::toTimestamp($this->endWallTime));
        }

        return $trace;
    }

    /**
     * Returns the node for a response path, creating it and any missing ancestors.
     *
     * No resolver ever fires for a list index, so those nodes only ever come into existence
     * here. Ancestors are walked up to the nearest known node and then created downwards, which
     * makes insertion order irrelevant -- deferred list items can settle in any order.
     *
     * @param array<int, string|int> $path
     */
    private function ensureNode(array $path): TraceNode
    {
        $key = implode('.', $path);
        if (isset($this->nodes[$key])) {
            return $this->nodes[$key];
        }

        $parent = $this->ensureNode(array_slice($path, 0, -1));

        $segment = end($path);
        $node = is_int($segment) ? TraceNode::forIndex($segment) : TraceNode::forResponseName((string) $segment);

        $parent->children[] = $node;
        $this->nodes[$key] = $node;
        ++$this->nodeCount;

        return $node;
    }

    /**
     * A resolver whose promise never settles -- an undispatched DataLoader, say -- would leave
     * end_time at 0, which Studio reads as a negative duration. Structural nodes, which never
     * carry timings at all, are left alone.
     */
    private function backfillEndTimes(TraceNode $node): void
    {
        if ($node->startTime > 0 && 0 === $node->endTime) {
            $node->endTime = $this->durationNs;
        }

        foreach ($node->children as $child) {
            $this->backfillEndTimes($child);
        }
    }

    private function toProtoNode(TraceNode $node): ProtoNode
    {
        $proto = new ProtoNode();

        if (null !== $node->responseName) {
            $proto->setResponseName($node->responseName);
        } elseif (null !== $node->index) {
            $proto->setIndex($node->index);
        }

        if ('' !== $node->type) {
            $proto->setType($node->type);
        }

        if ('' !== $node->parentType) {
            $proto->setParentType($node->parentType);
        }

        if ('' !== $node->originalFieldName) {
            $proto->setOriginalFieldName($node->originalFieldName);
        }

        $proto->setStartTime($node->startTime);
        $proto->setEndTime($node->endTime);

        if ([] !== $node->errors) {
            $proto->setError(array_map([self::class, 'toProtoError'], $node->errors));
        }

        if ([] !== $node->children) {
            $proto->setChild(array_map([$this, 'toProtoNode'], $node->children));
        }

        return $proto;
    }

    private static function toProtoError(TraceError $error): ProtoError
    {
        $proto = new ProtoError();
        $proto->setMessage($error->message);
        $proto->setJson($error->json);

        if ([] !== $error->locations) {
            $proto->setLocation(array_map(
                static fn (array $location): ProtoLocation => (new ProtoLocation())
                    ->setLine($location['line'])
                    ->setColumn($location['column']),
                $error->locations
            ));
        }

        return $proto;
    }

    private function elapsedNs(): int
    {
        if (null === $this->startHrTime) {
            return 0;
        }

        return hrtime(true) - $this->startHrTime;
    }

    private static function toTimestamp(float $wallTime): Timestamp
    {
        $seconds = (int) floor($wallTime);

        return new Timestamp([
            'seconds' => $seconds,
            'nanos' => (int) round(($wallTime - $seconds) * 1_000_000_000),
        ]);
    }
}
