<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config\Parser\GraphQL\ASTConverter;

use GraphQL\Language\AST\Node;
use Redeye\GraphQLBundle\CacheControl\CacheScope;
use RuntimeException;
use function count;
use function sprintf;

/**
 * Reads the `@cacheControl` and `@cacheTag` directives off a field or type definition node.
 *
 * ```graphql
 * directive @cacheControl(maxAge: Int, scope: CacheControlScope, inheritMaxAge: Boolean)
 *   on FIELD_DEFINITION | OBJECT | INTERFACE | UNION
 * directive @cacheTag(format: String!) repeatable on FIELD_DEFINITION | OBJECT
 * ```
 */
final class CacheDirectivesNode
{
    public const CACHE_CONTROL = 'cacheControl';
    public const CACHE_TAGS = 'cacheTags';

    private const CACHE_CONTROL_DIRECTIVE = 'cacheControl';
    private const CACHE_TAG_DIRECTIVE = 'cacheTag';

    private function __construct()
    {
    }

    /**
     * @return array{cacheControl?: array<string, mixed>, cacheTags?: string[]}
     */
    public static function toConfig(Node $node): array
    {
        $config = [];

        $cacheControl = self::cacheControl($node);
        if ([] !== $cacheControl) {
            $config[self::CACHE_CONTROL] = $cacheControl;
        }

        $cacheTags = self::cacheTags($node);
        if ([] !== $cacheTags) {
            $config[self::CACHE_TAGS] = $cacheTags;
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private static function cacheControl(Node $node): array
    {
        $directives = DirectiveArguments::named($node, self::CACHE_CONTROL_DIRECTIVE);

        if ([] === $directives) {
            return [];
        }

        if (count($directives) > 1) {
            throw new RuntimeException(sprintf('@%s is not repeatable.', self::CACHE_CONTROL_DIRECTIVE));
        }

        $directive = $directives[0];

        $config = [];

        $maxAge = DirectiveArguments::intArg($directive, 'maxAge');
        if (null !== $maxAge) {
            $config['maxAge'] = $maxAge;
        }

        $scope = DirectiveArguments::enumArg($directive, 'scope', CacheScope::ALL);
        if (null !== $scope) {
            $config['scope'] = $scope;
        }

        $inheritMaxAge = DirectiveArguments::boolArg($directive, 'inheritMaxAge');
        if (null !== $inheritMaxAge) {
            $config['inheritMaxAge'] = $inheritMaxAge;
        }

        // There is nothing to inherit if the annotation already decided a maxAge.
        if (isset($config['maxAge']) && !empty($config['inheritMaxAge'])) {
            throw new RuntimeException(sprintf(
                '@%s cannot set both "maxAge" and "inheritMaxAge".',
                self::CACHE_CONTROL_DIRECTIVE
            ));
        }

        return $config;
    }

    /**
     * @return string[]
     */
    private static function cacheTags(Node $node): array
    {
        $tags = [];

        foreach (DirectiveArguments::named($node, self::CACHE_TAG_DIRECTIVE) as $directive) {
            $format = DirectiveArguments::stringArg($directive, 'format');

            if (null === $format) {
                throw new RuntimeException(sprintf(
                    'Argument "format" is required on the @%s directive.',
                    self::CACHE_TAG_DIRECTIVE
                ));
            }

            $tags[] = $format;
        }

        return $tags;
    }
}
