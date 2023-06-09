<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config\Parser\GraphQL\ASTConverter;

use GraphQL\Language\AST\Node;
use GraphQL\Utils\AST;

class FieldsNode implements NodeInterface
{
    public static function toConfig(Node $node, string $property = 'fields'): array
    {
        $config = [];
        if (!empty($node->$property)) {
            foreach ($node->$property as $definition) {
                $fieldConfig = TypeNode::toConfig($definition) + DescriptionNode::toConfig($definition);

                if (!empty($definition->arguments)) {
                    $fieldConfig['args'] = self::toConfig($definition, 'arguments');
                }

                if (!empty($definition->defaultValue)) {
                    $fieldConfig['defaultValue'] = AST::valueFromASTUntyped($definition->defaultValue);
                }

                $directiveConfig = DirectiveNode::toConfig($definition);
                if (isset($directiveConfig['deprecationReason'])) {
                    $fieldConfig['deprecationReason'] = $directiveConfig['deprecationReason'];
                }

                if (isset($directiveConfig['resolve'])) {
                    $fieldConfig['resolve'] = $directiveConfig['resolve'];
                }

                if (isset($directiveConfig['shareable'])) {
                    $fieldConfig['shareable'] = $directiveConfig['shareable'];
                }

                if (isset($directiveConfig['external'])) {
                    $fieldConfig['external'] = $directiveConfig['external'];
                }

                if (isset($directiveConfig['requires'])) {
                    $fieldConfig['requires'] = $directiveConfig['requires'];
                }

                if (isset($directiveConfig['provides'])) {
                    $fieldConfig['provides'] = $directiveConfig['provides'];
                }

                if (isset($directiveConfig['override'])) {
                    $fieldConfig['override'] = $directiveConfig['override'];
                }

                $config[$definition->name->value] = $fieldConfig;
            }
        }

        return $config;
    }
}
