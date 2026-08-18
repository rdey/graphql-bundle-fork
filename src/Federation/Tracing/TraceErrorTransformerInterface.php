<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation\Tracing;

use GraphQL\Error\Error;

/**
 * Rewrites errors before they are recorded in an Apollo Federation inline trace.
 *
 * The PHP equivalent of Apollo Server's `includeErrors: { transform: ... }` option. Register an
 * implementation as a service and name it in `redeye_graphql.inline_trace.transformer_service`;
 * doing so overrides `include_errors`.
 *
 * Only the returned error's message and extensions are used. Its locations and path are ignored
 * in favour of the original error's, so a transformer cannot move an error onto a different
 * trace node.
 *
 * @see docs/federation/inline-trace.md
 */
interface TraceErrorTransformerInterface
{
    /**
     * @return Error|null the error to record, or null to omit it from the trace entirely
     */
    public function transform(Error $error): ?Error;
}
