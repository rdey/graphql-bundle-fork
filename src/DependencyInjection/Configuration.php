<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\DependencyInjection;

use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Redeye\GraphQLBundle\CacheControl\CacheControlAccumulator;
use Redeye\GraphQLBundle\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;
use Redeye\GraphQLBundle\Definition\Argument;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\ConfigParserPass;
use Redeye\GraphQLBundle\Error\ErrorHandler;
use Redeye\GraphQLBundle\EventListener\ErrorLoggerListener;
use Redeye\GraphQLBundle\Executor\Executor;
use Redeye\GraphQLBundle\ExpressionLanguage\ExpressionLanguage;
use Redeye\GraphQLBundle\Resolver\FieldResolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\EnumNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpKernel\Kernel;
use function array_keys;
use function is_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;
use function sprintf;

class Configuration implements ConfigurationInterface
{
    public const NAME = 'redeye_graphql';

    private bool $debug;

    /**
     * @param bool $debug Whether to use the debug mode
     */
    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::NAME);

        $rootNode = $treeBuilder->getRootNode();

        // @phpstan-ignore-next-line
        $rootNode
            ->children()
                ->booleanNode('federation')
                    ->defaultFalse()
                ->end()
                ->append($this->batchingMethodSection())
                ->append($this->definitionsSection())
                ->append($this->errorsHandlerSection())
                ->append($this->servicesSection())
                ->append($this->securitySection())
                ->append($this->doctrineSection())
                ->append($this->profilerSection())
                ->append($this->cacheControlSection())
            ->end();

        return $treeBuilder;
    }

    private function cacheControlSection(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('cache_control');

        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        // @phpstan-ignore-next-line
        $node
            ->info('Turns @cacheControl annotations into a Cache-Control response header.')
            ->treatFalseLike(['enabled' => false])
            ->treatTrueLike(['enabled' => true])
            ->treatNullLike(['enabled' => true])
            ->addDefaultsIfNotSet()
            ->children()
                // Opt-in: switching this on gives a Cache-Control header to responses that
                // previously had none, including no-store for anything unannotated.
                ->booleanNode('enabled')->defaultFalse()->end()
                ->integerNode('default_max_age')
                    ->min(0)
                    ->defaultValue(0)
                    ->info('maxAge applied to root fields and composite-returning fields that carry no hint.')
                ->end()
                ->enumNode('calculate_http_headers')
                    ->values([
                        CacheControlAccumulator::HEADERS_ALWAYS,
                        CacheControlAccumulator::HEADERS_IF_CACHEABLE,
                        CacheControlAccumulator::HEADERS_NEVER,
                    ])
                    ->defaultValue(CacheControlAccumulator::HEADERS_ALWAYS)
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => is_bool($v))
                        ->then(fn ($v) => $v ? CacheControlAccumulator::HEADERS_ALWAYS : CacheControlAccumulator::HEADERS_NEVER)
                    ->end()
                    ->info('"if-cacheable" leaves the header untouched instead of emitting no-store.')
                ->end()
            ->end();

        return $node;
    }

    private function batchingMethodSection(): EnumNodeDefinition
    {
        $builder = new TreeBuilder('batching_method', 'enum');

        /** @var EnumNodeDefinition $node */
        $node = $builder->getRootNode();

        $node
            ->values(['relay', 'apollo'])
            ->defaultValue('relay')
        ->end();

        return $node;
    }

    private function errorsHandlerSection(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('errors_handler');

        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        // @phpstan-ignore-next-line
        $node
            ->treatFalseLike(['enabled' => false])
            ->treatTrueLike(['enabled' => true])
            ->treatNullLike(['enabled' => true])
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('internal_error_message')->defaultValue(ErrorHandler::DEFAULT_ERROR_MESSAGE)->end()
                ->booleanNode('rethrow_internal_exceptions')->defaultFalse()->end()
                ->booleanNode('debug')->defaultValue($this->debug)->end()
                ->booleanNode('log')->defaultTrue()->end()
                ->scalarNode('logger_service')->defaultValue(ErrorLoggerListener::DEFAULT_LOGGER_SERVICE)->end()
                ->booleanNode('map_exceptions_to_parent')->defaultFalse()->end()
                ->arrayNode('exceptions')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('warnings')
                            ->treatNullLike([])
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('errors')
                            ->treatNullLike([])
                            ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function definitionsSection(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('definitions');
        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        // @phpstan-ignore-next-line
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('argument_class')->defaultValue(Argument::class)->end()
                ->scalarNode('use_experimental_executor')->defaultFalse()->end()
                ->scalarNode('default_field_resolver')->defaultValue(FieldResolver::class)->end()
                ->scalarNode('class_namespace')->defaultValue('Redeye\\GraphQLBundle\\__DEFINITIONS__')->end()
                ->scalarNode('cache_dir')->defaultNull()->end()
                ->scalarNode('cache_dir_permissions')->defaultNull()->end()
                ->booleanNode('use_classloader_listener')->defaultTrue()->end()
                ->scalarNode('auto_compile')->defaultTrue()->end()
                ->booleanNode('show_debug_info')->info('Show some performance stats in extensions')->defaultFalse()->end()
                ->booleanNode('config_validation')->defaultValue($this->debug)->end()
                ->append($this->definitionsSchemaSection())
                ->append($this->definitionsMappingsSection())
                ->arrayNode('builders')
                    ->children()
                        ->append($this->builderSection('field'))
                        ->append($this->builderSection('fields'))
                        ->append($this->builderSection('args'))
                    ->end()
                ->end()

            ->end()
        ->end();

        return $node;
    }

    private function servicesSection(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('services');

        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        // @phpstan-ignore-next-line
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('executor')
                    ->defaultValue(Executor::class)
                ->end()
                ->scalarNode('promise_adapter')
                    ->defaultValue(SyncPromiseAdapter::class)
                ->end()
                ->scalarNode('expression_language')
                    ->defaultValue(ExpressionLanguage::class)
                ->end()
                ->scalarNode('cache_expression_language_parser')->end()
            ->end()
        ->end();

        return $node;
    }

    private function securitySection(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('security');

        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        // @phpstan-ignore-next-line
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->append($this->securityQuerySection('query_max_depth', QueryDepth::DISABLED))
                ->append($this->securityQuerySection('query_max_complexity', QueryComplexity::DISABLED))
                ->booleanNode('enable_introspection')->defaultTrue()->end()
                ->booleanNode('handle_cors')->defaultFalse()->end()
            ->end()
        ->end();

        return $node;
    }

    private function definitionsSchemaSection(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('schema');

        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        if (Kernel::VERSION_ID >= 50100) {
            $deprecatedArgs = [
                'redeye/graphql-bundle',
                '0.13',
                'The "%path%.%node%" configuration is deprecated and will be removed in 1.0. Add the "redeye_graphql.resolver_map" tag to the services instead.',
            ];
        } else {
            $deprecatedArgs = ['The "%path%.%node%" configuration is deprecated since version 0.13 and will be removed in 1.0. Add the "redeye_graphql.resolver_map" tag to the services instead.'];
        }

        // @phpstan-ignore-next-line
        $node
            ->beforeNormalization()
                ->ifTrue(fn ($v) => isset($v['query']) && is_string($v['query']) || isset($v['mutation']) && is_string($v['mutation']))
                ->then(fn ($v) => ['default' => $v])
            ->end()
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('query')->defaultNull()->end()
                    ->scalarNode('mutation')->defaultNull()->end()
                    ->scalarNode('subscription')->defaultNull()->end()
                    ->arrayNode('resolver_maps')
                        ->defaultValue([])
                        ->prototype('scalar')->end()
                        ->setDeprecated(...$deprecatedArgs)
                    ->end()
                    ->arrayNode('types')
                        ->defaultValue([])
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }

    private function definitionsMappingsSection(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('mappings');

        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        // @phpstan-ignore-next-line
        $node
            ->children()
                ->arrayNode('auto_discover')
                    ->treatFalseLike(['bundles' => false, 'root_dir' => false, 'built_in' => true])
                    ->treatTrueLike(['bundles' => true, 'root_dir' => true, 'built_in' => true])
                    ->treatNullLike(['bundles' => true, 'root_dir' => true, 'built_in' => true])
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('bundles')->defaultFalse()->end()
                        ->booleanNode('root_dir')->defaultFalse()->end()
                        ->booleanNode('built_in')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('types')
                    ->prototype('array')
                        ->addDefaultsIfNotSet()
                        ->beforeNormalization()
                            ->ifTrue(fn ($v) => isset($v['type']) && is_string($v['type']))
                            ->then(function ($v) {
                                if ('yml' === $v['type']) {
                                    $v['types'] = ['yaml'];
                                } else {
                                    $v['types'] = [$v['type']];
                                }
                                unset($v['type']);

                                return $v;
                            })
                        ->end()
                        ->children()
                            ->arrayNode('types')
                                ->prototype('enum')->values(array_keys(ConfigParserPass::SUPPORTED_TYPES_EXTENSIONS))->isRequired()->end()
                            ->end()
                            ->scalarNode('dir')->defaultNull()->end()
                            ->scalarNode('suffix')->defaultValue(ConfigParserPass::DEFAULT_TYPES_SUFFIX)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function doctrineSection(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('doctrine');

        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        // @phpstan-ignore-next-line
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('types_mapping')
                    ->defaultValue([])
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;

        return $node;
    }

    private function profilerSection(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('profiler');

        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        // @phpstan-ignore-next-line
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('query_match')->defaultNull()->end()
            ->end()
        ;

        return $node;
    }

    private function builderSection(string $name): ArrayNodeDefinition
    {
        $builder = new TreeBuilder($name);

        /** @var ArrayNodeDefinition $node */
        $node = $builder->getRootNode();

        $node->beforeNormalization()
            ->ifTrue(fn ($v) => is_array($v) && !empty($v))
            ->then(function ($v) {
                foreach ($v as $key => &$config) {
                    if (is_string($config)) {
                        $config = [
                            'alias' => $key,
                            'class' => $config,
                        ];
                    }
                }

                return $v;
            })
        ->end();

        // @phpstan-ignore-next-line
        $node->prototype('array')
            ->children()
                ->scalarNode('alias')->isRequired()->end()
                ->scalarNode('class')->isRequired()->end()
            ->end()
        ->end()
        ;

        return $node;
    }

    /**
     * @param mixed $disabledValue
     */
    private function securityQuerySection(string $name, $disabledValue): ScalarNodeDefinition
    {
        $builder = new TreeBuilder($name, 'scalar');

        /** @var ScalarNodeDefinition $node */
        $node = $builder->getRootNode();

        $node->beforeNormalization()
                ->ifTrue(fn ($v) => is_string($v) && is_numeric($v))
                ->then(fn ($v) => (int) $v)
            ->end();

        $node
            ->info('Disabled if equal to false.')
            ->beforeNormalization()
                ->ifTrue(fn ($v) => false === $v)
                ->then(fn () => $disabledValue)
            ->end()
            ->defaultValue($disabledValue)
            ->validate()
                ->ifTrue(fn ($v) => is_int($v) && $v < 0)
                ->thenInvalid(sprintf('"%s.security.%s" must be greater or equal to 0.', self::NAME, $name))
            ->end()
        ;

        return $node;
    }
}
