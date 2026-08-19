<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\CacheControl;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use function sprintf;

/**
 * Aggregates the cache policies of every operation executed during one HTTP request, and turns the
 * result into a `Cache-Control` response header.
 *
 * The batch endpoint runs several operations in a single request; the aggregate is the most
 * restrictive of them, which is the same rule that already applies across the fields of one query.
 */
final class CacheControlAccumulator
{
    public const HEADERS_ALWAYS = 'always';
    public const HEADERS_IF_CACHEABLE = 'if-cacheable';
    public const HEADERS_NEVER = 'never';

    private const HEADER = 'Cache-Control';
    private const UNCACHEABLE = 'no-store';

    private CacheHintResolver $hintResolver;
    private bool $enabled;
    private int $defaultMaxAge;
    private string $headerMode;

    private CachePolicy $overall;
    private bool $uncacheable = false;
    private ?CacheControlRecorder $current = null;

    public function __construct(
        CacheHintResolver $hintResolver,
        bool $enabled = false,
        int $defaultMaxAge = 0,
        string $headerMode = self::HEADERS_ALWAYS
    ) {
        $this->hintResolver = $hintResolver;
        $this->enabled = $enabled;
        $this->defaultMaxAge = $defaultMaxAge;
        $this->headerMode = $headerMode;
        $this->overall = new CachePolicy();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Starts a fresh recorder for one operation. The caller puts it in the GraphQL context value.
     */
    public function startOperation(): CacheControlRecorder
    {
        return $this->current = new CacheControlRecorder($this->hintResolver, $this->defaultMaxAge);
    }

    /**
     * Merges the finished operation into the request-wide policy.
     *
     * An operation that produced errors makes the whole response uncacheable, and stays that way
     * however many further operations succeed.
     */
    public function finishOperation(bool $hasErrors): void
    {
        if ($hasErrors) {
            $this->uncacheable = true;
        } elseif (null !== $this->current) {
            $this->overall->restrictBy($this->current->getPolicy());
        }

        $this->current = null;
    }

    /**
     * Under php-fpm the container is rebuilt per request, but under a worker runtime it is not.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->reset();
        }
    }

    /**
     * Clears the accumulated policy. The hint resolver's memo is deliberately untouched — it is
     * derived from the schema, not from the request.
     */
    public function reset(): void
    {
        $this->overall->reset();
        $this->uncacheable = false;
        $this->current = null;
    }

    /**
     * Writes the aggregated policy onto the response.
     *
     * Symfony normalises `Cache-Control` on the way out: a value carrying neither `public`,
     * `private` nor `s-maxage` gets `, private` appended, so the uncacheable case is emitted as
     * `no-store, private` rather than Apollo's bare `no-store`. That is not reachable through
     * Symfony's Response API — `setCache()` goes through the same code — and it is semantically
     * identical, since no cache may store the response either way.
     *
     * In the `if-cacheable` mode nothing is written at all for an uncacheable response, which
     * leaves Symfony's own default of `no-cache, private` in place.
     */
    public function applyTo(Response $response): void
    {
        if (!$this->enabled || self::HEADERS_NEVER === $this->headerMode) {
            return;
        }

        if (!$this->uncacheable && $this->overall->isCacheable()) {
            $response->headers->set(self::HEADER, sprintf(
                'max-age=%d, %s',
                $this->overall->getMaxAge(),
                CacheScope::toHeaderToken($this->overall->getScope())
            ));

            return;
        }

        if (self::HEADERS_IF_CACHEABLE === $this->headerMode) {
            return;
        }

        $response->headers->set(self::HEADER, self::UNCACHEABLE);
    }

    public function getPolicy(): CachePolicy
    {
        return $this->overall;
    }

    public function isUncacheable(): bool
    {
        return $this->uncacheable;
    }
}
