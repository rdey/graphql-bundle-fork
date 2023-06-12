<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DataLoader;

use GraphQL\Executor\Promise\Promise;
use Redeye\GraphQLBundle\PromiseAdapter\PromiseAdapterInterface;

/**
 * @template TKey
 * @template T
 * @template-extends DataLoader<TKey, T>
 */
abstract class AbstractDataLoader extends DataLoader
{
    /**
     * @param array<TKey> $keys
     * @return Promise<array<T>>|array<T>
     */
    abstract protected function batchLoad(array $keys);

    /**
     * @param PromiseAdapterInterface<Promise<T>> $promiseFactory
     */
    public function __construct(PromiseAdapterInterface $promiseFactory, Option $options = null)
    {
        parent::__construct(fn($keys) => $this->batchLoad($keys), $promiseFactory, $options);
    }

    /**
     * @param array<TKey> $keys
     * @param array<T> $data
     * @param callable(T|TKey): string $hasher
     * @return array<T|null>
     */
    protected function matchToInput(array $keys, array $data, callable $hasher): array
    {
        $keyHashes = array_map($hasher, $keys);

        $dataHashes = array_map($hasher, $data);
        $dataLookup = array_combine($dataHashes, $data);

        return array_map(fn (string $keyHash) => $dataLookup[$keyHash] ?? null, $keyHashes);
    }

    /**
     * @param T $v
     * @return Promise<T>
     */
    public function fulfilledPromise($v): Promise
    {
        return $this->getPromiseAdapter()->createFulfilled($v);
    }
}