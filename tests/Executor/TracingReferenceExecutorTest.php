<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Executor;

use ArrayObject;
use GraphQL\Deferred;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\EventListener\InlineTraceListener;
use Redeye\GraphQLBundle\Executor\TracingReferenceExecutor;
use Redeye\GraphQLBundle\Federation\Tracing\TraceNode;
use Redeye\GraphQLBundle\Federation\Tracing\TraceTreeBuilder;
use RuntimeException;

/**
 * Exercises the per-field hook through a real graphql-php execution, because that is the only
 * way to cover the interactions that matter: list index paths, aliasing, and deferred fields.
 */
final class TracingReferenceExecutorTest extends TestCase
{
    /** @var callable */
    private $previousFactory;

    protected function setUp(): void
    {
        $this->previousFactory = Executor::getImplementationFactory();
        Executor::setImplementationFactory([TracingReferenceExecutor::class, 'create']);
    }

    protected function tearDown(): void
    {
        Executor::setImplementationFactory($this->previousFactory);
    }

    private function schema(): Schema
    {
        $user = new ObjectType([
            'name' => 'User',
            'fields' => [
                'name' => [
                    'type' => Type::string(),
                    'resolve' => static fn (array $user): string => $user['name'],
                ],
                'slowName' => [
                    'type' => Type::string(),
                    // A deferred field: the value is not available when the resolver returns.
                    'resolve' => static fn (array $user): Deferred => new Deferred(static function () use ($user): string {
                        usleep(3000);

                        return $user['name'];
                    }),
                ],
                'broken' => [
                    'type' => Type::string(),
                    'resolve' => static function (): string {
                        throw new RuntimeException('resolver exploded');
                    },
                ],
            ],
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'me' => [
                        'type' => Type::nonNull($user),
                        'resolve' => static fn (): array => ['name' => 'Ada'],
                    ],
                    'users' => [
                        'type' => Type::listOf($user),
                        'resolve' => static fn (): array => [['name' => 'Ada'], ['name' => 'Grace']],
                    ],
                ],
            ]),
        ]);
    }

    /**
     * @return array{0: TraceTreeBuilder, 1: \GraphQL\Executor\ExecutionResult}
     */
    private function execute(string $query, bool $withTracing = true): array
    {
        $builder = new TraceTreeBuilder();
        $builder->startTiming();

        $context = new ArrayObject();
        if ($withTracing) {
            $context[InlineTraceListener::CONTEXT_KEY] = $builder;
        }

        $result = GraphQL::executeQuery($this->schema(), $query, null, $context);

        return [$builder, $result];
    }

    private function childNamed(TraceNode $node, string $responseName): TraceNode
    {
        foreach ($node->children as $child) {
            if ($responseName === $child->responseName) {
                return $child;
            }
        }

        self::fail(sprintf('no child named "%s"', $responseName));
    }

    public function testRecordsANodePerResolvedField(): void
    {
        [$builder, $result] = $this->execute('{ me { name } }');

        $this->assertSame(['me' => ['name' => 'Ada']], $result->data);

        $me = $this->childNamed($builder->getRoot(), 'me');
        $this->assertSame('User!', $me->type);
        $this->assertSame('Query', $me->parentType);
        $this->assertGreaterThan(0, $me->endTime);

        $name = $this->childNamed($me, 'name');
        $this->assertSame('String', $name->type);
        $this->assertSame('User', $name->parentType);
    }

    public function testRecordsIndexNodesForListItems(): void
    {
        [$builder] = $this->execute('{ users { name } }');

        $users = $this->childNamed($builder->getRoot(), 'users');
        $this->assertCount(2, $users->children);

        $this->assertSame(0, $users->children[0]->index);
        $this->assertSame(1, $users->children[1]->index);
        $this->assertSame('name', $users->children[0]->children[0]->responseName);
        $this->assertSame('User', $users->children[0]->children[0]->parentType);
    }

    public function testRecordsTheSchemaFieldNameForAnAliasedField(): void
    {
        [$builder] = $this->execute('{ currentUser: me { name } }');

        $node = $this->childNamed($builder->getRoot(), 'currentUser');
        $this->assertSame('me', $node->originalFieldName);
    }

    public function testRecordsTypenameFields(): void
    {
        [$builder] = $this->execute('{ me { __typename } }');

        $me = $this->childNamed($builder->getRoot(), 'me');
        $this->assertSame('__typename', $this->childNamed($me, '__typename')->responseName);
    }

    /**
     * The end time of a deferred field must be recorded when its value settles, not when the
     * resolver returns the promise. Recording synchronously would give a duration of very nearly
     * zero; the resolver sleeps 3ms, so the gap is unmistakable.
     */
    public function testRecordsTheEndTimeOfADeferredFieldWhenItSettles(): void
    {
        [$builder] = $this->execute('{ me { slowName } }');

        $slowName = $this->childNamed($this->childNamed($builder->getRoot(), 'me'), 'slowName');

        $this->assertGreaterThan(
            2_000_000,
            $slowName->endTime - $slowName->startTime,
            'a deferred field was timed synchronously instead of on settle'
        );
    }

    public function testDeferredFieldsStillProduceOrderedTimings(): void
    {
        [$builder] = $this->execute('{ me { slowName name } }');

        $me = $this->childNamed($builder->getRoot(), 'me');
        foreach ($me->children as $child) {
            $this->assertGreaterThanOrEqual($child->startTime, $child->endTime, $child->responseName);
            $this->assertGreaterThan(0, $child->endTime, $child->responseName);
        }
    }

    public function testRecordsAFieldWhoseResolverThrows(): void
    {
        [$builder, $result] = $this->execute('{ me { broken } }');

        $this->assertCount(1, $result->errors);

        $broken = $this->childNamed($this->childNamed($builder->getRoot(), 'me'), 'broken');
        $this->assertSame('String', $broken->type);
        $this->assertGreaterThan(0, $broken->endTime, 'a throwing resolver must still be timed');
    }

    public function testExecutesNormallyWithNoTraceInTheContext(): void
    {
        [$builder, $result] = $this->execute('{ me { name } }', false);

        $this->assertSame(['me' => ['name' => 'Ada']], $result->data);
        $this->assertSame([], $builder->getRoot()->children);
    }

    public function testReturnsTheOriginalResolverValueForDeferredFields(): void
    {
        [, $result] = $this->execute('{ me { slowName } }');

        $this->assertSame(['me' => ['slowName' => 'Ada']], $result->data);
        $this->assertSame([], $result->errors);
    }
}
