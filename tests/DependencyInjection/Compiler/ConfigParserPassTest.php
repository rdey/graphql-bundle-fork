<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\DependencyInjection\Compiler;

use GraphQL\Error\UserError;
use PHPUnit\Framework\TestCase;
use Redeye\GraphQLBundle\Config\Processor\InheritanceProcessor;
use Redeye\GraphQLBundle\DependencyInjection\Compiler\ConfigParserPass;
use Redeye\GraphQLBundle\DependencyInjection\RedeyeGraphQLExtension;
use Redeye\GraphQLBundle\Error\ExceptionConverter;
use Redeye\GraphQLBundle\Error\UserWarning;
use Redeye\GraphQLBundle\Tests\Config\Parser\MetadataParserTest;
use Redeye\GraphQLBundle\Tests\DependencyInjection\Builder\BoxFields;
use Redeye\GraphQLBundle\Tests\DependencyInjection\Builder\MutationField;
use Redeye\GraphQLBundle\Tests\DependencyInjection\Builder\PagerArgs;
use Redeye\GraphQLBundle\Tests\DependencyInjection\Builder\RawIdField;
use Redeye\GraphQLBundle\Tests\DependencyInjection\Builder\TimestampFields;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use function preg_quote;
use function sprintf;
use const DIRECTORY_SEPARATOR;

class ConfigParserPassTest extends TestCase
{
    private ContainerBuilder $container;
    private ConfigParserPass $compilerPass;

    public function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.bundles', []);
        $this->container->setParameter('kernel.debug', false);
        $this->compilerPass = new ConfigParserPass();
    }

    public function tearDown(): void
    {
        unset($this->container, $this->compilerPass);
    }

    public function testBrokenYmlOnPrepend(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('#The file "(.*)' . preg_quote(DIRECTORY_SEPARATOR) . 'broken.types.yml" does not contain valid YAML\.#');
        $this->processCompilerPass($this->getMappingConfig('yaml'));
    }

    public function testPreparseOnPrepend(): void
    {
        if (!MetadataParserTest::isDoctrineAnnotationInstalled()) {
            $this->markTestSkipped('doctrine/annotations not installed. Skipping test.');
        }
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "redeye_graphql_types.Type._object_config.fields" should have at least 1 element(s) defined.');
        $this->processCompilerPass($this->getMappingConfig('annotation'));
    }

    /**
     * @dataProvider internalConfigKeys
     */
    public function testInternalConfigKeysShouldNotBeUsed(string $internalConfigKey): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Don\'t use internal config keys _object_config, _entity_object_config, _entity_ref_object_config, _enum_config, _interface_config, _union_config, _input_object_config, _custom_scalar_config, replace it by "config" instead.');
        $configs = [
            ['bar' => [$internalConfigKey => []]],
        ];

        $this->compilerPass->processConfiguration($configs);
    }

    /**
     * @dataProvider fieldBuilderTypeOverrideNotAllowedProvider
     * @runInSeparateProcess
     */
    public function testFieldBuilderTypeOverrideNotAllowed(array $builders, array $configs, string $exceptionClass, string $exceptionMessage): void
    {
        $ext = new RedeyeGraphQLExtension();
        $ext->load(
            [
                ['definitions' => ['builders' => $builders]],
            ],
            $this->container
        );

        $this->expectException($exceptionClass); // @phpstan-ignore-line
        $this->expectExceptionMessage($exceptionMessage);

        $this->compilerPass->processConfiguration([$configs]);
    }

    /**
     * @runInSeparateProcess
     */
    public function testCustomExceptions(): void
    {
        $ext = new RedeyeGraphQLExtension();
        $ext->load(
            [
                [
                    'errors_handler' => [
                        'exceptions' => [
                            'warnings' => [
                                ResourceNotFoundException::class,
                            ],
                            'errors' => [
                                \InvalidArgumentException::class,
                            ],
                        ],
                    ],
                ],
            ],
            $this->container
        );

        $expectedExceptionMap = [
            ResourceNotFoundException::class => UserWarning::class,
            \InvalidArgumentException::class => UserError::class,
        ];

        $definition = $this->container->getDefinition(ExceptionConverter::class);

        $this->assertSame($expectedExceptionMap, $definition->getArgument(0));
    }

    /**
     * @runInSeparateProcess
     */
    public function testCustomBuilders(): void
    {
        $ext = new RedeyeGraphQLExtension();
        $ext->load(
            [
                [
                    'definitions' => [
                        'builders' => [
                            'field' => [
                                'RawId' => RawIdField::class,
                                'Mutation' => MutationField::class,
                            ],
                            'fields' => [
                                'Timestamps' => TimestampFields::class,
                                'Boxes' => BoxFields::class,
                            ],
                            'args' => [
                                [
                                    'alias' => 'Pager',
                                    'class' => PagerArgs::class,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $this->container
        );

        $config = $this->compilerPass->processConfiguration(
            [
                [
                    'foo' => [
                        'type' => 'object',
                        'config' => [
                            'builders' => [
                                [
                                    'builder' => 'Timestamps',
                                    'builderConfig' => ['param1' => 'val1'],
                                ],
                            ],
                            'fields' => [
                                'rawIDWithDescriptionOverride' => [
                                    'builder' => 'RawId',
                                    'description' => 'rawIDWithDescriptionOverride description',
                                ],
                                'rawID' => ['builder' => 'RawId'],
                                'rawIDs' => [
                                    'type' => '[RawID!]!',
                                    'argsBuilder' => 'Pager',
                                ],
                                'categories' => [
                                    'type' => '[String!]!',
                                    'argsBuilder' => ['builder' => 'Pager'],
                                ],
                                'categories2' => [
                                    'type' => '[String!]!',
                                    'argsBuilder' => ['builder' => 'Pager', 'config' => ['defaultLimit' => 50]],
                                ],
                            ],
                        ],
                    ],
                    'Boxes' => [
                        'type' => 'object',
                        'config' => [
                            'builders' => [
                                [
                                    'builder' => 'Boxes',
                                    'builderConfig' => [
                                        'foo' => 'Foo',
                                        'bar' => 'Bar',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'Mutation' => [
                        'type' => 'object',
                        'config' => [
                            'fields' => [
                                'foo' => [
                                    'builder' => 'Mutation',
                                    'builderConfig' => [
                                        'name' => 'Foo',
                                        'resolver' => 'Mutation.foo',
                                        'inputFields' => [
                                            'bar' => ['type' => 'String!'],
                                        ],
                                        'payloadFields' => [
                                            'fooString' => ['type' => 'String!'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $this->assertSame(
            [
                'foo' => [
                    'type' => 'object',
                    'class_name' => 'fooType',
                    InheritanceProcessor::INHERITS_KEY => [],
                    'decorator' => false,
                    'config' => [
                        'fields' => [
                            'createdAt' => [
                                'description' => 'The creation date of the object',
                                'type' => 'Int!',
                                'resolve' => '@=value.createdAt',
                                'shareable' => false,
                                'external' => false,
                            ],
                            'updatedAt' => [
                                'description' => 'The update date of the object',
                                'type' => 'Int!',
                                'resolve' => '@=value.updatedAt',
                                'shareable' => false,
                                'external' => false,
                            ],
                            'rawIDWithDescriptionOverride' => [
                                'description' => 'rawIDWithDescriptionOverride description',
                                'type' => 'Int!',
                                'resolve' => '@=value.id',
                                'shareable' => false,
                                'external' => false,
                            ],
                            'rawID' => [
                                'description' => 'The raw ID of an object',
                                'type' => 'Int!',
                                'resolve' => '@=value.id',
                                'shareable' => false,
                                'external' => false,
                            ],
                            'rawIDs' => [
                                'type' => '[RawID!]!',
                                'args' => [
                                    'limit' => [
                                        'type' => 'Int!',
                                        'defaultValue' => 20,
                                    ],
                                    'offset' => [
                                        'type' => 'Int!',
                                        'defaultValue' => 0,
                                    ],
                                ],
                                'shareable' => false,
                                'external' => false,
                            ],
                            'categories' => [
                                'type' => '[String!]!',
                                'args' => [
                                    'limit' => [
                                        'type' => 'Int!',
                                        'defaultValue' => 20,
                                    ],
                                    'offset' => [
                                        'type' => 'Int!',
                                        'defaultValue' => 0,
                                    ],
                                ],
                                'shareable' => false,
                                'external' => false,
                            ],
                            'categories2' => [
                                'type' => '[String!]!',
                                'args' => [
                                    'limit' => [
                                        'type' => 'Int!',
                                        'defaultValue' => 50,
                                    ],
                                    'offset' => [
                                        'type' => 'Int!',
                                        'defaultValue' => 0,
                                    ],
                                 ],
                                'shareable' => false,
                                'external' => false,
                            ],
                        ],
                        'name' => 'foo',
                        'builders' => [],
                        'shareable' => false,
                        'external' => false,
                        'interfaces' => [],
                    ],
                ],
                'Boxes' => [
                    'type' => 'object',
                    'class_name' => 'BoxesType',
                    InheritanceProcessor::INHERITS_KEY => [],
                    'decorator' => false,
                    'config' => [
                        'fields' => [
                            'foo' => [
                                'type' => 'FooBox!',
                                'shareable' => false,
                                'external' => false,
                            ],
                            'bar' => [
                                'type' => 'BarBox!',
                                'shareable' => false,
                                'external' => false,
                            ],
                        ],
                        'name' => 'Boxes',
                        'builders' => [],
                        'shareable' => false,
                        'external' => false,
                        'interfaces' => [],
                    ],
                ],
                'Mutation' => [
                    'type' => 'object',
                    'class_name' => 'MutationType',
                    InheritanceProcessor::INHERITS_KEY => [],
                    'decorator' => false,
                    'config' => [
                        'fields' => [
                            'foo' => [
                                'type' => 'FooPayload!',
                                'resolve' => '@=mutation("Mutation.foo", args.input)',
                                'args' => [
                                    'input' => ['type' => 'FooInput!'],
                                ],
                                'shareable' => false,
                                'external' => false,
                            ],
                        ],
                        'name' => 'Mutation',
                        'builders' => [],
                        'shareable' => false,
                        'external' => false,
                        'interfaces' => [],
                    ],
                ],
                'FooBox' => [
                    'type' => 'object',
                    'class_name' => 'FooBoxType',
                    InheritanceProcessor::INHERITS_KEY => [],
                    'decorator' => false,
                    'config' => [
                        'fields' => [
                            'isEmpty' => [
                                'type' => 'Boolean!',
                                'shareable' => false,
                                'external' => false,
                            ],
                            'item' => [
                                'type' => 'Foo',
                                'shareable' => false,
                                'external' => false,
                            ],
                        ],
                        'name' => 'FooBox',
                        'builders' => [],
                        'shareable' => false,
                        'external' => false,
                        'interfaces' => [],
                    ],
                ],
                'BarBox' => [
                    'type' => 'object',
                    'class_name' => 'BarBoxType',
                    InheritanceProcessor::INHERITS_KEY => [],
                    'decorator' => false,
                    'config' => [
                        'fields' => [
                            'isEmpty' => [
                                'type' => 'Boolean!',
                                'shareable' => false,
                                'external' => false,
                            ],
                            'item' => [
                                'type' => 'Bar',
                                'shareable' => false,
                                'external' => false,
                            ],
                        ],
                        'name' => 'BarBox',
                        'builders' => [],
                        'shareable' => false,
                        'external' => false,
                        'interfaces' => [],
                    ],
                ],
                'FooInput' => [
                    'type' => 'input-object',
                    'class_name' => 'FooInputType',
                    InheritanceProcessor::INHERITS_KEY => [],
                    'decorator' => false,
                    'config' => [
                        'fields' => [
                            'bar' => ['type' => 'String!'],
                        ],
                        'name' => 'FooInput',
                    ],
                ],
                'FooPayload' => [
                    'type' => 'union',
                    'class_name' => 'FooPayloadType',
                    InheritanceProcessor::INHERITS_KEY => [],
                    'decorator' => false,
                    'config' => [
                        'types' => ['FooSuccessPayload', 'FooFailurePayload'],
                        'resolveType' => '@=query("PayloadTypeResolver", value, "FooSuccessPayload", "FooFailurePayload")',
                        'name' => 'FooPayload',
                    ],
                ],
                'FooSuccessPayload' => [
                    'type' => 'object',
                    'class_name' => 'FooSuccessPayloadType',
                    InheritanceProcessor::INHERITS_KEY => [],
                    'decorator' => false,
                    'config' => [
                        'fields' => [
                            'fooString' => [
                                'type' => 'String!',
                                'shareable' => false,
                                'external' => false,
                            ],
                        ],
                        'name' => 'FooSuccessPayload',
                        'builders' => [],
                        'shareable' => false,
                        'external' => false,
                        'interfaces' => [],
                    ],
                ],
                'FooFailurePayload' => [
                    'type' => 'object',
                    'class_name' => 'FooFailurePayloadType',
                    InheritanceProcessor::INHERITS_KEY => [],
                    'decorator' => false,
                    'config' => [
                        'fields' => [
                            '_error' => [
                                'type' => 'String',
                                'shareable' => false,
                                'external' => false,
                            ],
                            'bar' => [
                                'type' => 'String',
                                'shareable' => false,
                                'external' => false,
                            ],
                        ],
                        'name' => 'FooFailurePayload',
                        'builders' => [],
                        'shareable' => false,
                        'external' => false,
                        'interfaces' => [],
                    ],
                ],
            ],
            $config
        );
    }

    public function internalConfigKeys(): array
    {
        return [
            ['_object_config'],
            ['_enum_config'],
            ['_interface_config'],
            ['_union_config'],
            ['_input_object_config'],
        ];
    }

    private function getMappingConfig(string $type): array
    {
        return [
            'definitions' => [
                'mappings' => [
                    'types' => [
                        [
                            'types' => [$type],
                            'dir' => __DIR__ . '/../mapping/' . $type,
                        ],
                    ],
                ],
            ],
            'doctrine' => ['types_mapping' => []],
        ];
    }

    public function fieldBuilderTypeOverrideNotAllowedProvider(): array
    {
        $expectedMessage = 'Type "%s" emitted by builder "%s" already exists. Type was provided by "%s". Builder may only emit new types. Overriding is not allowed.';

        $simpleObjectType = [
            'type' => 'object',
            'config' => [
                'fields' => [
                    'value' => ['type' => 'String'],
                ],
            ],
        ];

        $mutationFieldBuilder = [
            'builder' => 'Mutation',
            'builderConfig' => [
                'name' => 'Foo',
                'resolver' => 'Mutation.foo',
                'inputFields' => [
                    'bar' => ['type' => 'String!'],
                ],
                'payloadFields' => [
                    'fooString' => ['type' => 'String!'],
                ],
            ],
        ];

        $boxFieldsBuilders = [
            [
                'builder' => 'Boxes',
                'builderConfig' => [
                    'foo' => 'Foo',
                    'bar' => 'Bar',
                ],
            ],
        ];

        return [
            [
                ['field' => ['Mutation' => MutationField::class]],
                [
                    'Mutation' => [
                        'type' => 'object',
                        'config' => [
                            'fields' => [
                                'foo' => $mutationFieldBuilder,
                            ],
                        ],
                    ],
                    'FooInput' => $simpleObjectType,
                ],
                InvalidConfigurationException::class,
                sprintf($expectedMessage, 'FooInput', MutationField::class, 'configs'),
            ],
            [
                ['field' => ['Mutation' => MutationField::class]],
                [
                    'Mutation' => [
                        'type' => 'object',
                        'config' => [
                            'fields' => [
                                'foo' => $mutationFieldBuilder,
                                'bar' => $mutationFieldBuilder,
                            ],
                        ],
                    ],
                ],
                InvalidConfigurationException::class,
                sprintf($expectedMessage, 'FooInput', MutationField::class, MutationField::class),
            ],
            [
                ['fields' => ['Boxes' => BoxFields::class]],
                [
                    'Boxes' => [
                        'type' => 'object',
                        'config' => [
                            'builders' => $boxFieldsBuilders,
                        ],
                    ],
                    'FooBox' => $simpleObjectType,
                ],
                InvalidConfigurationException::class,
                sprintf($expectedMessage, 'FooBox', BoxFields::class, 'configs'),
            ],
            [
                ['fields' => ['Boxes' => BoxFields::class]],
                [
                    'Boxes' => [
                        'type' => 'object',
                        'config' => [
                            'builders' => $boxFieldsBuilders,
                        ],
                    ],
                    'OtherBoxes' => [
                        'type' => 'object',
                        'config' => [
                            'builders' => $boxFieldsBuilders,
                        ],
                    ],
                ],
                InvalidConfigurationException::class,
                sprintf($expectedMessage, 'FooBox', BoxFields::class, BoxFields::class),
            ],
        ];
    }

    private function processCompilerPass(array $configs, ?ConfigParserPass $compilerPass = null, ?ContainerBuilder $container = null): void
    {
        $container ??= $this->container;
        $compilerPass ??= $this->compilerPass;
        $container->setParameter('redeye_graphql.config', $configs);
        $compilerPass->process($container);
    }
}
