<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config\Parser\GraphQL\ASTConverter;

use GraphQL\Language\AST\DirectiveNode as ASTDirectiveNode;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\Directive;

class DirectiveNode implements NodeInterface
{
    public static function toConfig(Node $node): array
    {
        $config = [];

        foreach ($node->directives as $directiveDef) {
            if ('deprecated' === $directiveDef->name->value) {
                $reason = $directiveDef->arguments->count() ?
                    $directiveDef->arguments[0]->value->value : Directive::DEFAULT_DEPRECATION_REASON;

                $config['deprecationReason'] = $reason;
                break;
            }

            if ('resolve' === $directiveDef->name->value) {
                $config['resolve'] = $directiveDef->arguments[0]->value->value;
            }

            if ('shareable' === $directiveDef->name->value) {
                $config['shareable'] = true;
            }

            if ('external' === $directiveDef->name->value) {
                $config['external'] = true;
            }

            if ('requires' === $directiveDef->name->value) {
                $config['requires'] = $directiveDef->arguments[0]->value->value;
            }

            if ('provides' === $directiveDef->name->value) {
                $config['provides'] = $directiveDef->arguments[0]->value->value;
            }

            if ('override' === $directiveDef->name->value) {
                $config['override'] = $directiveDef->arguments[0]->value->value;
            }
        }

        return $config;
    }
}
