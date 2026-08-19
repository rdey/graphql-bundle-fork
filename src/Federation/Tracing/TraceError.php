<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation\Tracing;

/**
 * An error, already reduced to what an FTV1 trace carries.
 *
 * Produced by {@see TraceErrorFilter} so that {@see TraceTreeBuilder} never needs to know
 * anything about graphql-php's error types or about the configured masking policy.
 *
 * @internal
 */
final class TraceError
{
    /**
     * @param array<int, array{line: int, column: int}> $locations 1-based source locations
     * @param string                                    $json      the `{message, locations, path, extensions}`
     *                                                             serialization of the policy-applied error
     */
    public function __construct(
        public string $message,
        public array $locations,
        public string $json
    ) {
    }
}
