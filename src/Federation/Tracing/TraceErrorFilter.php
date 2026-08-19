<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation\Tracing;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;

/**
 * Applies the configured error policy and reduces an error to what a trace carries.
 *
 * Port of Apollo Server's `chooseErrorTransform` / `errorToProtobufError`.
 *
 * @see docs/federation/inline-trace.md
 */
final class TraceErrorFilter
{
    public const MASKED = 'masked';
    public const UNMODIFIED = 'unmodified';

    public const MASKED_MESSAGE = '<masked>';
    public const MASKED_BY = 'RedeyeGraphQLBundleInlineTrace';

    private string $includeErrors;
    private string $internalErrorMessage;
    private ?TraceErrorTransformerInterface $transformer;

    public function __construct(
        string $includeErrors,
        string $internalErrorMessage,
        ?TraceErrorTransformerInterface $transformer = null
    ) {
        $this->includeErrors = $includeErrors;
        $this->internalErrorMessage = $internalErrorMessage;
        $this->transformer = $transformer;
    }

    /**
     * @return TraceError|null null when the policy says to omit this error from the trace
     */
    public function filter(Error $error): ?TraceError
    {
        $reported = $this->applyPolicy($error);

        if (null === $reported) {
            return null;
        }

        $locations = [];
        foreach ($error->getLocations() as $location) {
            $locations[] = ['line' => $location->line, 'column' => $location->column];
        }

        return new TraceError($reported->getMessage(), $locations, (string) json_encode($reported));
    }

    /**
     * Rebuilds the error with a policy-approved message and extensions, always keeping the
     * original's nodes, source, positions and path so that it stays attached to the same trace
     * node and reports the same locations.
     */
    private function applyPolicy(Error $error): ?Error
    {
        if (null !== $this->transformer) {
            $transformed = $this->transformer->transform($error);

            if (null === $transformed) {
                return null;
            }

            return $this->rebuild($error, $transformed->getMessage(), $transformed->getExtensions() ?? []);
        }

        if (self::UNMODIFIED === $this->includeErrors) {
            // NOT $error->getMessage(): the bundle's ErrorHandler masks at response-format time,
            // so the raw exception text (SQL, DSNs, file paths) is still on the Error object
            // here. Report what the client would see instead.
            $formatted = FormattedError::createFromException($error, DebugFlag::NONE, $this->internalErrorMessage);

            return $this->rebuild($error, (string) $formatted['message'], $formatted['extensions'] ?? []);
        }

        return $this->rebuild($error, self::MASKED_MESSAGE, ['maskedBy' => self::MASKED_BY]);
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function rebuild(Error $error, string $message, array $extensions): Error
    {
        return new Error(
            $message,
            $error->nodes,
            $error->getSource(),
            $error->getPositions(),
            $error->path,
            null,
            $extensions
        );
    }
}
