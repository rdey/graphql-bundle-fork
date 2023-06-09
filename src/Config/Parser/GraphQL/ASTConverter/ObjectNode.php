<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config\Parser\GraphQL\ASTConverter;

use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DirectiveNode as ASTDirectiveNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\StringValueNode;

class ObjectNode implements NodeInterface
{
    protected const TYPENAME = 'object';

    /**
     * @param ObjectTypeDefinitionNode $node
     */
    public static function toConfig(Node $node): array
    {
        $config = DescriptionNode::toConfig($node) + [
            'fields' => FieldsNode::toConfig($node),
        ];

        if (!empty($node->interfaces)) {
            $interfaces = [];
            foreach ($node->interfaces as $interface) {
                $interfaces[] = TypeNode::astTypeNodeToString($interface);
            }
            $config['interfaces'] = $interfaces;
        }

        $hasKeyDirective = false;
        $isResolvable = true;
        $keyFields = [];
        foreach (self::directivesNamed($node, 'key') as $directive) {
            $hasKeyDirective = true;

            $resolvableNode = self::directiveArgumentValue($directive, 'resolvable', BooleanValueNode::class);
            if ($resolvableNode && $resolvableNode->value === false) {
                $isResolvable = false;
            }

            $fieldsNode = self::directiveArgumentValue($directive, 'fields', StringValueNode::class);
            if (!$fieldsNode) {
                throw new \RuntimeException("Argument 'fields' missing on @key directive");
            }
            $keyFields[] = $fieldsNode->value;
        }

        if ($hasKeyDirective) {
            $config['keyFields'] = $keyFields;
        }

        if (count(self::directivesNamed($node, 'shareable')) > 0) {
            $config['shareable'] = true;
        }

        if (count(self::directivesNamed($node, 'external')) > 0) {
            $config['external'] = true;
        }

        if ($hasKeyDirective && $isResolvable) {
            $referenceResolvers = self::directivesNamed($node, 'resolveReference');
            if (1 !== count($referenceResolvers)) {
                throw new \RuntimeException("Entity requires exactly one reference resolver, %d given", count($referenceResolvers));
            }

            $expr = self::directiveArgumentValue($referenceResolvers[0], 'expression', StringValueNode::class);

            if (!$expr) {
                throw new \RuntimeException("Directive @resolveReference must have an argument named 'expression'");
            }

            $config['resolveReference'] = $expr->value;
        }

        return [
            'type' => self::typeName($hasKeyDirective, $isResolvable),
            'config' => $config,
        ];
    }

    private static function typeName(bool $hasKeyDirective, bool $isResolvable): string
    {
        if ($hasKeyDirective) {
            return $isResolvable ? 'entity-object' : 'entity-ref-object';
        }

        return static::TYPENAME;
    }

    /**
     * @return array<ASTDirectiveNode>
     */
    private static function directivesNamed(Node $node, string $directiveName): array
    {
        $directives = [];
        foreach ($node->directives as $directive) {
            if ($directive->name->value === $directiveName) {
                $directives[] = $directive;
            }
        }

        return $directives;
    }

    /**
     * @template T of Node
     * @param class-string<T> $valueNodeClass
     * @phpstan-return T|null
     */
    private static function directiveArgumentValue(ASTDirectiveNode $directive, string $argumentName, string $valueNodeClass): ?Node
    {
        foreach ($directive->arguments as $argument) {
            if ($argument->name->value === $argumentName) {
                if ($argument->value instanceof $valueNodeClass) {
                    return $argument->value;
                } else {
                    throw new \RuntimeException("Expected value type to be $valueNodeClass, but was ".get_class($argument->value));
                }
            }
        }

        return null;
    }
}
