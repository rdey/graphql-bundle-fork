<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Federation\Tracing;

use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\Federation\Tracing\TraceError;
use Redeye\GraphQLBundle\Federation\Tracing\TraceNode;
use Redeye\GraphQLBundle\Federation\Tracing\TraceTreeBuilder;
use function count;

final class TraceTreeBuilderTest extends TestCase
{
    private Schema $schema;
    private ObjectType $query;
    private ObjectType $user;

    protected function setUp(): void
    {
        $this->user = new ObjectType([
            'name' => 'User',
            'fields' => [
                'name' => ['type' => Type::string()],
            ],
        ]);

        $this->query = new ObjectType([
            'name' => 'Query',
            'fields' => [
                'me' => ['type' => Type::nonNull($this->user)],
                'users' => ['type' => Type::listOf($this->user)],
            ],
        ]);

        $this->schema = new Schema(['query' => $this->query]);
    }

    /**
     * @param list<string|int> $path
     */
    private function resolveInfo(ObjectType $parentType, string $fieldName, array $path): ResolveInfo
    {
        return new ResolveInfo(
            $parentType->getField($fieldName),
            [],
            $parentType,
            $path,
            $this->schema,
            [],
            null,
            new OperationDefinitionNode([
                'name' => new NameNode(['value' => 'Test']),
                'operation' => 'query',
                'selectionSet' => new SelectionSetNode(['selections' => []]),
                'variableDefinitions' => [],
                'directives' => [],
            ]),
            []
        );
    }

    private function startedBuilder(): TraceTreeBuilder
    {
        $builder = new TraceTreeBuilder();
        $builder->startTiming();

        return $builder;
    }

    public function testRootNodeHasNoIdentityAndNoTimings(): void
    {
        $root = $this->startedBuilder()->getRoot();

        $this->assertNull($root->responseName);
        $this->assertNull($root->index);
        $this->assertSame('', $root->type);
        $this->assertSame('', $root->parentType);
        $this->assertSame(0, $root->startTime);
        $this->assertSame(0, $root->endTime);
    }

    public function testResolvedFieldBecomesChildOfRootWithTypeAndParentType(): void
    {
        $builder = $this->startedBuilder();

        $builder->willResolveField($this->resolveInfo($this->query, 'me', ['me']));

        $children = $builder->getRoot()->children;
        $this->assertCount(1, $children);
        $this->assertSame('me', $children[0]->responseName);
        $this->assertSame('User!', $children[0]->type);
        $this->assertSame('Query', $children[0]->parentType);
    }

    public function testEndCallbackRecordsEndTimeAfterStartTime(): void
    {
        $builder = $this->startedBuilder();

        $end = $builder->willResolveField($this->resolveInfo($this->query, 'me', ['me']));
        $end();

        $node = $builder->getRoot()->children[0];
        $this->assertGreaterThan(0, $node->endTime);
        $this->assertGreaterThanOrEqual($node->startTime, $node->endTime);
    }

    public function testAliasedFieldRecordsOriginalFieldName(): void
    {
        $builder = $this->startedBuilder();

        // `{ currentUser: me { ... } }` -- path carries the alias, fieldName the schema name.
        $builder->willResolveField($this->resolveInfo($this->query, 'me', ['currentUser']));

        $node = $builder->getRoot()->children[0];
        $this->assertSame('currentUser', $node->responseName);
        $this->assertSame('me', $node->originalFieldName);
    }

    public function testUnaliasedFieldLeavesOriginalFieldNameEmpty(): void
    {
        $builder = $this->startedBuilder();

        $builder->willResolveField($this->resolveInfo($this->query, 'me', ['me']));

        $this->assertSame('', $builder->getRoot()->children[0]->originalFieldName);
    }

    /**
     * No resolver ever fires for a list index, so the builder has to synthesise the
     * intermediate node when a descendant is resolved.
     */
    public function testListIndexAncestorIsSynthesised(): void
    {
        $builder = $this->startedBuilder();

        $builder->willResolveField($this->resolveInfo($this->user, 'name', ['users', 0, 'name']));

        $users = $builder->getRoot()->children[0];
        $this->assertSame('users', $users->responseName);
        $this->assertSame('', $users->type, 'a synthesised ancestor has no type');

        $index = $users->children[0];
        $this->assertNull($index->responseName);
        $this->assertSame(0, $index->index);
        $this->assertSame('', $index->type, 'an index node is structural only');
        $this->assertSame(0, $index->startTime);

        $name = $index->children[0];
        $this->assertSame('name', $name->responseName);
        $this->assertSame('String', $name->type);
        $this->assertSame('User', $name->parentType);
    }

    public function testSiblingListItemsShareOneParentNode(): void
    {
        $builder = $this->startedBuilder();

        $builder->willResolveField($this->resolveInfo($this->user, 'name', ['users', 0, 'name']));
        $builder->willResolveField($this->resolveInfo($this->user, 'name', ['users', 1, 'name']));

        $users = $builder->getRoot()->children;
        $this->assertCount(1, $users, 'both items must hang off a single `users` node');
        $this->assertCount(2, $users[0]->children);
        $this->assertSame(0, $users[0]->children[0]->index);
        $this->assertSame(1, $users[0]->children[1]->index);
    }

    public function testAncestorSynthesisIsOrderIndependent(): void
    {
        $builder = $this->startedBuilder();

        // Deferred items can settle out of order.
        $builder->willResolveField($this->resolveInfo($this->user, 'name', ['users', 2, 'name']));
        $builder->willResolveField($this->resolveInfo($this->user, 'name', ['users', 0, 'name']));

        $indices = array_map(
            static fn (TraceNode $n): ?int => $n->index,
            $builder->getRoot()->children[0]->children
        );
        $this->assertSame([2, 0], $indices);
    }

    /**
     * graphql-php reuses one ResolveInfo across list items and mutates its `path` in
     * completeListValue(). An end callback that re-derives the path at settle time would
     * therefore write into the wrong node -- it must capture the node itself.
     */
    public function testEndCallbackIsImmuneToResolveInfoPathMutation(): void
    {
        $builder = $this->startedBuilder();

        $info = $this->resolveInfo($this->user, 'name', ['users', 0, 'name']);
        $end = $builder->willResolveField($info);

        $info->path = ['users', 1, 'name'];
        $end();

        $indexZero = $builder->getRoot()->children[0]->children[0];
        $this->assertSame(0, $indexZero->index);
        $this->assertGreaterThan(0, $indexZero->children[0]->endTime, 'end time landed on the wrong node');
    }

    public function testErrorWithMatchingPathAttachesToThatNode(): void
    {
        $builder = $this->startedBuilder();
        $builder->willResolveField($this->resolveInfo($this->query, 'me', ['me']));

        $builder->addError(['me'], new TraceError('boom', [], '{}'));

        $this->assertCount(0, $builder->getRoot()->errors);
        $this->assertCount(1, $builder->getRoot()->children[0]->errors);
        $this->assertSame('boom', $builder->getRoot()->children[0]->errors[0]->message);
    }

    public function testErrorWithoutPathAttachesToRoot(): void
    {
        $builder = $this->startedBuilder();

        $builder->addError(null, new TraceError('Syntax Error', [], '{}'));

        $this->assertCount(1, $builder->getRoot()->errors);
        $this->assertSame('Syntax Error', $builder->getRoot()->errors[0]->message);
    }

    public function testErrorWithUnresolvedPathCreatesTheNode(): void
    {
        $builder = $this->startedBuilder();

        $builder->addError(['users', 0, 'name'], new TraceError('boom', [], '{}'));

        $name = $builder->getRoot()->children[0]->children[0]->children[0];
        $this->assertSame('name', $name->responseName);
        $this->assertCount(1, $name->errors);
    }

    public function testStopTimingSetsDurationAndFieldExecutionWeight(): void
    {
        $builder = $this->startedBuilder();
        $builder->stopTiming();

        $trace = $builder->toProto();
        $this->assertGreaterThan(0, (int) $trace->getDurationNs());
        $this->assertSame(1.0, $trace->getFieldExecutionWeight());
        $this->assertGreaterThan(0, (int) $trace->getStartTime()->getSeconds());
        $this->assertGreaterThan(0, (int) $trace->getEndTime()->getSeconds());
    }

    /**
     * A resolver that returns a promise which never settles (e.g. an undispatched DataLoader)
     * would otherwise leave end_time at 0, which reads as a negative duration in Studio.
     */
    public function testStopTimingBackfillsMissingEndTimes(): void
    {
        $builder = $this->startedBuilder();
        $builder->willResolveField($this->resolveInfo($this->query, 'me', ['me']));

        $builder->stopTiming();

        $node = $builder->getRoot()->children[0];
        $this->assertSame((int) $builder->toProto()->getDurationNs(), $node->endTime);
    }

    public function testStopTimingLeavesStructuralNodesUntouched(): void
    {
        $builder = $this->startedBuilder();
        $builder->willResolveField($this->resolveInfo($this->user, 'name', ['users', 0, 'name']));

        $builder->stopTiming();

        $index = $builder->getRoot()->children[0]->children[0];
        $this->assertSame(0, $index->startTime);
        $this->assertSame(0, $index->endTime, 'index nodes never carry timings');
    }

    public function testStopsRecordingOnceTheNodeCapIsReached(): void
    {
        $builder = new TraceTreeBuilder(3);
        $builder->startTiming();

        for ($i = 0; $i < 10; ++$i) {
            $end = $builder->willResolveField($this->resolveInfo($this->query, 'me', ['me'.$i]));
            $end();
        }

        $this->assertLessThanOrEqual(3, count($builder->getRoot()->children));
    }

    public function testProtoMirrorsTheBuiltTree(): void
    {
        $builder = $this->startedBuilder();
        $end = $builder->willResolveField($this->resolveInfo($this->user, 'name', ['users', 0, 'name']));
        $end();
        $builder->addError(['users', 0, 'name'], new TraceError('boom', [['line' => 2, 'column' => 5]], '{"m":1}'));
        $builder->stopTiming();

        $root = $builder->toProto()->getRoot();

        $users = $root->getChild()[0];
        $this->assertSame('users', $users->getResponseName());

        $index = $users->getChild()[0];
        $this->assertSame(0, $index->getIndex());

        $name = $index->getChild()[0];
        $this->assertSame('name', $name->getResponseName());
        $this->assertSame('String', $name->getType());
        $this->assertSame('User', $name->getParentType());
        $this->assertGreaterThan(0, (int) $name->getEndTime());

        $error = $name->getError()[0];
        $this->assertSame('boom', $error->getMessage());
        $this->assertSame('{"m":1}', $error->getJson());
        $this->assertSame(2, $error->getLocation()[0]->getLine());
        $this->assertSame(5, $error->getLocation()[0]->getColumn());
    }
}
