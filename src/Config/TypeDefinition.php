<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config;

use Redeye\GraphQLBundle\CacheControl\CacheScope;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;

abstract class TypeDefinition
{
    public const VALIDATION_LEVEL_CLASS = 0;
    public const VALIDATION_LEVEL_PROPERTY = 1;

    abstract public function getDefinition(): ArrayNodeDefinition;

    final protected function __construct()
    {
    }

    /**
     * @return static
     */
    public static function create(): self
    {
        return new static();
    }

    protected function resolveTypeSection(): VariableNodeDefinition
    {
        return self::createNode('resolveType', 'variable');
    }

    protected function nameSection(): ScalarNodeDefinition
    {
        /** @var ScalarNodeDefinition $node */
        $node = self::createNode('name', 'scalar');

        $node
            ->isRequired()
            ->validate()
                ->ifTrue(fn ($name) => !preg_match('/^[_a-z][_0-9a-z]*$/i', $name))
                ->thenInvalid('Invalid type name "%s". (see http://spec.graphql.org/June2018/#sec-Names)')
            ->end()
        ;

        return $node;
    }

    protected function defaultValueSection(): VariableNodeDefinition
    {
        return self::createNode('defaultValue', 'variable');
    }

    protected function validationSection(int $level): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $node */
        $node = self::createNode('validation', 'array');

        /** @phpstan-ignore-next-line */
        $node
            // allow shorthands
            ->beforeNormalization()
                ->always(function ($value) {
                    if (is_string($value)) {
                        // shorthand: cascade or link
                        return 'cascade' === $value ? ['cascade' => null] : ['link' => $value];
                    }

                    if (is_array($value)) {
                        foreach ($value as $k => $a) {
                            if (!is_int($k)) {
                                // validation: { link: ... , constraints: ..., cascade: ... }
                                return $value;
                            }
                        }
                        // validation: [list of constraints]
                        return ['constraints' => $value];
                    }

                    return [];
                })
            ->end()
            ->children()
                ->scalarNode('link')
                    ->validate()
                        ->ifTrue(function ($link) use ($level) {
                            if (self::VALIDATION_LEVEL_PROPERTY === $level) {
                                return !preg_match('/^(?:\\\\?[A-Za-z][A-Za-z\d]+)*[A-Za-z\d]+::(?:[$]?[A-Za-z][A-Za-z_\d]+|[A-Za-z_\d]+\(\))$/m', $link);
                            } else {
                                return !preg_match('/^(?:\\\\?[A-Za-z][A-Za-z\d]+)*[A-Za-z\d]$/m', $link);
                            }
                        })
                        ->thenInvalid('Invalid link provided: "%s".')
                    ->end()
                ->end()
                ->variableNode('constraints')->end()
            ->end();

        // Add the 'cascade' option if it's a property level validation section
        if (self::VALIDATION_LEVEL_PROPERTY === $level) {
            /** @phpstan-ignore-next-line */
            $node
                ->children()
                    ->arrayNode('cascade')
                        ->children()
                            ->arrayNode('groups')
                                ->beforeNormalization()
                                    ->castToArray()
                                ->end()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();
        }

        return $node;
    }

    protected function descriptionSection(): ScalarNodeDefinition
    {
        /** @var ScalarNodeDefinition $node */
        $node = self::createNode('description', 'scalar');

        return $node;
    }

    protected function deprecationReasonSection(): ScalarNodeDefinition
    {
        /** @var ScalarNodeDefinition $node */
        $node = self::createNode('deprecationReason', 'scalar');

        $node->info('Text describing why this field is deprecated. When not empty - field will not be returned by introspection queries (unless forced)');

        return $node;
    }

    /**
     * The `@cacheControl` directive, usable on a field or on an object, interface or union type.
     *
     * Deliberately has neither `addDefaultsIfNotSet()` nor per-child defaults: the caching
     * algorithm distinguishes "this annotation did not mention maxAge" from "maxAge is 0", and
     * defaults here would silently annotate every type in the schema.
     */
    protected function cacheControlSection(): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $node */
        $node = self::createNode('cacheControl');

        /** @phpstan-ignore-next-line */
        $node
            ->info('HTTP cache hint for this field or type.')
            ->children()
                ->integerNode('maxAge')
                    ->min(0)
                    ->info('How long, in seconds, a response containing this may be cached.')
                ->end()
                ->enumNode('scope')
                    ->values(CacheScope::ALL)
                    ->info('PRIVATE marks the value as specific to a single user.')
                ->end()
                ->booleanNode('inheritMaxAge')
                    ->info('Inherit the parent\'s maxAge instead of defaulting to uncacheable.')
                ->end()
            ->end()
            ->validate()
                ->ifTrue(fn ($value) => isset($value['maxAge']) && !empty($value['inheritMaxAge']))
                ->thenInvalid('"cacheControl" cannot set both "maxAge" and "inheritMaxAge".')
            ->end();

        return $node;
    }

    /**
     * The `@cacheTag` directive. Repeatable, so this is a list of format strings.
     */
    protected function cacheTagsSection(): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $node */
        $node = self::createNode('cacheTags');

        /** @phpstan-ignore-next-line */
        $node
            ->info('Cache tags, passed through to the federated SDL for the router to interpret.')
            ->beforeNormalization()
                ->castToArray()
            ->end()
            ->scalarPrototype()->end();

        return $node;
    }

    /**
     * Drops an empty `cacheTags` entry from a config array.
     *
     * A prototyped array node always reports a default of `[]`, and Symfony applies defaults
     * without running the node's own validators, so the key has to be removed from the parent —
     * the same way this class's `validationGroups` and `args` are handled. Left in place it would
     * add a meaningless empty array to every field and type in every schema.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    protected static function unsetEmptyCacheTags(array $config): array
    {
        if (empty($config['cacheTags'])) {
            unset($config['cacheTags']);
        }

        return $config;
    }

    protected function typeSection(bool $isRequired = false): ScalarNodeDefinition
    {
        /** @var ScalarNodeDefinition $node */
        $node = self::createNode('type', 'scalar');

        $node->info('One of internal or custom types.');

        if ($isRequired) {
            $node->isRequired();
        }

        return $node;
    }

    /**
     * @return mixed
     *
     * @internal
     */
    protected static function createNode(string $name, string $type = 'array')
    {
        return (new TreeBuilder($name, $type))->getRootNode();
    }
}
