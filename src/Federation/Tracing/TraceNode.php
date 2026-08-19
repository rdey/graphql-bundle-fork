<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation\Tracing;

/**
 * A node in an in-progress FTV1 trace tree.
 *
 * Deliberately a plain struct rather than the generated `Proto\Trace\Node`: a large list
 * response produces tens of thousands of nodes, and the protobuf objects (two RepeatedFields
 * each) are an order of magnitude heavier. TraceTreeBuilder::toProto() converts the whole tree
 * once, at the end of the request, which also keeps protobuf off the per-field hot path.
 *
 * @internal
 */
final class TraceNode
{
    /**
     * The response key (the alias, when the query used one). Null on the root node and on
     * list-index nodes.
     */
    public ?string $responseName = null;

    /** The list position. Null on everything except list-index nodes. */
    public ?int $index = null;

    /** The field's return type, e.g. "String!". Empty on structural nodes. */
    public string $type = '';

    /** The field's parent type, e.g. "User". Empty on structural nodes. */
    public string $parentType = '';

    /** The schema field name, set only when {@see $responseName} is an alias. */
    public string $originalFieldName = '';

    /** Nanoseconds since the start of the trace. */
    public int $startTime = 0;

    /** Nanoseconds since the start of the trace. */
    public int $endTime = 0;

    /** @var TraceError[] */
    public array $errors = [];

    /** @var TraceNode[] */
    public array $children = [];

    public static function forResponseName(string $responseName): self
    {
        $node = new self();
        $node->responseName = $responseName;

        return $node;
    }

    public static function forIndex(int $index): self
    {
        $node = new self();
        $node->index = $index;

        return $node;
    }
}
