<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Config\Parser;

use Exception;
use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Redeye\GraphQLBundle\Config\Parser\GraphQL\ASTConverter\NodeInterface;
use Redeye\GraphQLBundle\Federation\Directives;
use SplFileInfo;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use function array_keys;
use function array_pop;
use function call_user_func;
use function explode;
use function file_get_contents;
use function in_array;
use function preg_replace;
use function sprintf;
use function trim;
use function ucfirst;

class GraphQLParser implements ParserInterface
{
    private const DEFINITION_TYPE_MAPPING = [
        NodeKind::OBJECT_TYPE_DEFINITION => 'object',
        NodeKind::INTERFACE_TYPE_DEFINITION => 'interface',
        NodeKind::ENUM_TYPE_DEFINITION => 'enum',
        NodeKind::UNION_TYPE_DEFINITION => 'union',
        NodeKind::INPUT_OBJECT_TYPE_DEFINITION => 'inputObject',
        NodeKind::SCALAR_TYPE_DEFINITION => 'customScalar',
    ];

    public static function parse(SplFileInfo $file, ContainerBuilder $container, array $configs = []): array
    {
        $container->addResource(new FileResource($file->getRealPath()));
        $content = trim((string) file_get_contents($file->getPathname()));
        $typesConfig = [];

        // allow empty files
        if (empty($content)) {
            return [];
        }
        try {
            $ast = Parser::parse($content);
        } catch (Exception $e) {
            throw new InvalidArgumentException(sprintf('An error occurred while parsing the file "%s".', $file), $e->getCode(), $e);
        }

        foreach ($ast->definitions as $typeDef) {
            /**
             * @var ObjectTypeDefinitionNode|InputObjectTypeDefinitionNode|EnumTypeDefinitionNode|DirectiveDefinitionNode $typeDef
             */
            if (isset($typeDef->kind) && in_array($typeDef->kind, array_keys(self::DEFINITION_TYPE_MAPPING))) {
                /** @var class-string<NodeInterface> $class */
                $class = sprintf('\\%s\\GraphQL\\ASTConverter\\%sNode', __NAMESPACE__, ucfirst(self::DEFINITION_TYPE_MAPPING[$typeDef->kind]));
                $typesConfig[$typeDef->name->value] = call_user_func([$class, 'toConfig'], $typeDef);
            } elseif (self::isAllowedDirectiveDefinition($typeDef)) {
                // Allow a directive named resolve
            } else {
                self::throwUnsupportedDefinitionNode($typeDef);
            }
        }

        return $typesConfig;
    }

    /**
     * @param ObjectTypeDefinitionNode|InputObjectTypeDefinitionNode|EnumTypeDefinitionNode $typeDef
     */
    private static function isAllowedDirectiveDefinition($typeDef): bool
    {
        if (!(isset($typeDef->kind) && NodeKind::DIRECTIVE_DEFINITION == $typeDef->kind && isset($typeDef->name))) {
            return false;
        }

        if (in_array($typeDef->name->value, ['resolve', 'resolveReference'])) {
            return true;
        }

        foreach (Directives::getDirectives() as $directive) {
            if ($directive->name === $typeDef->name->value) {
                return true;
            }
        }

        return false;
    }

    private static function throwUnsupportedDefinitionNode(DefinitionNode $typeDef): void
    {
        $path = explode('\\', $typeDef::class);
        throw new InvalidArgumentException(sprintf('%s definition is not supported right now.', preg_replace('@DefinitionNode$@', '', array_pop($path))));
    }
}
