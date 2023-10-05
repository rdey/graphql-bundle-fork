<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DataLoader;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\Uuid;

final class StringCacheKey
{
    /**
     * @param mixed|UuidInterface $key
     */
    public static function key($key): string
    {
        if ($key instanceof UuidInterface) {
            return $key->toString();
        }

        if ($key instanceof Uuid) {
            return $key->toRfc4122();
        }

        if ($key instanceof StringCacheKeyInterface) {
            return $key->toCacheKey();
        }

        if (is_array($key)) {
            $value = json_encode($key);

            if (!$value) {
                throw new \InvalidArgumentException('Unencodable array given as a cache key');
            }

            return $value;
        }

        if (is_object($key)) {
            return spl_object_hash($key);
        }

        if (is_resource($key)) {
            throw new \InvalidArgumentException('Resource given as a cache key');
        }

        if (is_null($key)) {
            throw new \InvalidArgumentException('Null given as a cache key');
        }

        /** @var string|float|int|bool $key */
        return (string) $key;
    }
}
