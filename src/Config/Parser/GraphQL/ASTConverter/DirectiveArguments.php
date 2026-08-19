<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config\Parser\GraphQL\ASTConverter;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DirectiveNode as ASTDirectiveNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use RuntimeException;
use function get_class;
use function implode;
use function in_array;
use function sprintf;

/**
 * Reads directive arguments off the AST by name and by expected value type.
 *
 * {@see DirectiveNode} reaches for `$directive->arguments[0]->value->value`, which works only for a
 * directive with a single string argument. Anything with named arguments of mixed types, or a
 * directive that may be repeated, needs these instead.
 */
final class DirectiveArguments
{
    private function __construct()
    {
    }

    /**
     * All applications of a directive on a node. More than one is possible for repeatable
     * directives such as `@cacheTag`.
     *
     * @return ASTDirectiveNode[]
     */
    public static function named(Node $node, string $directiveName): array
    {
        $directives = [];

        // Node itself declares no $directives; only the definition subclasses that can carry them
        // do, and graphql-php v14 has no common interface for those.
        /** @phpstan-ignore-next-line */
        foreach ($node->directives ?? [] as $directive) {
            if ($directive->name->value === $directiveName) {
                $directives[] = $directive;
            }
        }

        return $directives;
    }

    public static function intArg(ASTDirectiveNode $directive, string $name): ?int
    {
        $value = self::valueNode($directive, $name);

        if (null === $value) {
            return null;
        }

        if (!$value instanceof IntValueNode) {
            throw self::wrongType($directive, $name, 'an Int', $value);
        }

        // IntValueNode holds its value as a string.
        return (int) $value->value;
    }

    /**
     * @param string[] $allowed
     */
    public static function enumArg(ASTDirectiveNode $directive, string $name, array $allowed): ?string
    {
        $value = self::valueNode($directive, $name);

        if (null === $value) {
            return null;
        }

        // Tooling emits enum arguments both bare and quoted, so accept either.
        if (!$value instanceof EnumValueNode && !$value instanceof StringValueNode) {
            throw self::wrongType($directive, $name, 'an enum value', $value);
        }

        if (!in_array($value->value, $allowed, true)) {
            throw new RuntimeException(sprintf(
                'Argument "%s" of @%s must be one of %s, got "%s".',
                $name,
                $directive->name->value,
                implode(', ', $allowed),
                $value->value
            ));
        }

        return $value->value;
    }

    public static function boolArg(ASTDirectiveNode $directive, string $name): ?bool
    {
        $value = self::valueNode($directive, $name);

        if (null === $value) {
            return null;
        }

        if (!$value instanceof BooleanValueNode) {
            throw self::wrongType($directive, $name, 'a Boolean', $value);
        }

        return $value->value;
    }

    public static function stringArg(ASTDirectiveNode $directive, string $name): ?string
    {
        $value = self::valueNode($directive, $name);

        if (null === $value) {
            return null;
        }

        if (!$value instanceof StringValueNode) {
            throw self::wrongType($directive, $name, 'a String', $value);
        }

        return $value->value;
    }

    /**
     * Reads a named argument as a raw value node, or null when the argument is absent.
     *
     * @phpstan-template T of Node
     *
     * @phpstan-param class-string<T> $valueNodeClass
     *
     * @phpstan-return T|null
     */
    public static function typedArg(ASTDirectiveNode $directive, string $name, string $valueNodeClass): ?Node
    {
        $value = self::valueNode($directive, $name);

        if (null === $value) {
            return null;
        }

        if (!$value instanceof $valueNodeClass) {
            throw new RuntimeException(sprintf('Expected value type to be %s, but was %s', $valueNodeClass, get_class($value)));
        }

        return $value;
    }

    private static function valueNode(ASTDirectiveNode $directive, string $name): ?Node
    {
        foreach ($directive->arguments as $argument) {
            if ($argument->name->value === $name) {
                return $argument->value;
            }
        }

        return null;
    }

    private static function wrongType(ASTDirectiveNode $directive, string $name, string $expected, Node $actual): RuntimeException
    {
        return new RuntimeException(sprintf(
            'Argument "%s" of @%s must be %s, got %s.',
            $name,
            $directive->name->value,
            $expected,
            $actual->kind
        ));
    }
}
