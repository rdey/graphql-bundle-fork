<?php

declare(strict_types=1);

namespace Overblog\GraphQLBundle\Tests\Functional\Generator;

use Overblog\GraphQLBundle\Generator\Exception\GeneratorException;
use Overblog\GraphQLBundle\Tests\Functional\App\Validator;
use Overblog\GraphQLBundle\Tests\Functional\TestCase;
use function json_decode;

class TypeGeneratorTest extends TestCase
{
    public function testPublicCallback(): void
    {
        $expected = [
            'data' => [
                'object' => [
                    'name' => 'His name',
                    'privateData' => 'ThisIsPrivate',
                ],
            ],
        ];

        $this->assertResponse('query { object { name privateData } }', $expected, self::USER_ADMIN, 'public');

        $this->assertSame(
            'Cannot query field "privateData" on type "ObjectWithPrivateField".',
            json_decode( // @phpstan-ignore-next-line
                static::query(
                    'query { object { name privateData } }',
                    self::USER_RYAN,
                    'public'
                )->getResponse()->getContent() ?: '',
                true
            )['errors'][0]['message']
        );

        $expectedWithoutPrivateData = $expected;
        unset($expectedWithoutPrivateData['data']['object']['privateData']);

        $this->assertResponse('query { object { name } }', $expectedWithoutPrivateData, self::USER_RYAN, 'public');
    }

    public function testFieldDefaultPublic(): void
    {
        $this->assertSame(
            'Cannot query field "other" on type "ObjectWithPrivateField".',
            json_decode( // @phpstan-ignore-next-line
                static::query(
                    'query { object { name other } }',
                    self::USER_RYAN,
                    'public'
                )->getResponse()->getContent() ?: '',
                true
            )['errors'][0]['message']
        );
    }

    /**
     * Defining the `cascade` validation option on scalar types
     * should throw an exception.
     */
    public function testCascadeOnScalarasThrowsException(): void
    {
        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('Cascade validation cannot be applied to built-in types.');

        parent::setUp();
        static::bootKernel(['test_case' => 'cascadeOnScalars']);
    }

    /**
     * Defining a validation constraint which doesn't exist should
     * throw an exception.
     */
    public function testNonExistentConstraintThrowsException(): void
    {
        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage("Constraint class 'Symfony\Component\Validator\Constraints\BlahBlah' doesn't exist.");

        parent::setUp();
        static::bootKernel(['test_case' => 'nonexistentConstraint']);
    }

    /**
     * Injecting the `validator` constraint into a resolver without having
     * any constraints defined in the type should throw an exception.
     */
    public function testInjectValidatorWithoutConstraintsThrowsException(): void
    {
        $this->expectException(GeneratorException::class);
        $this->expectExceptionMessage('Unable to inject an instance of the InputValidator. No validation constraints provided. Please remove the "validator" argument from the list of dependencies of your resolver or provide validation configs.');

        parent::setUp();
        static::bootKernel(['test_case' => 'validatorWithoutConstraints']);
    }

    /**
     * In generated type definitions:
     *  1. Custom constraints should be used by FQCN, to ensure no namespace conflicts occur
     *  2. Default Symfony validators should be used with aliased namespace to ensure that no
     *     namespace conflicts occur with Graphql bundle classes (Type for example).
     */
    public function testConflictingValidatorNamespaces(): void
    {
        parent::setUp();
        static::bootKernel(['test_case' => 'conflictingValidatorNamespaces']);

        $query = <<<'EOF'
            mutation {
              conflictingValidatorNamespaces(test: "123", test2: "1", test3: "4")
            }
            EOF;

        $response = static::query(
            $query,
            self::USER_RYAN,
            'conflictingValidatorNamespaces'
        )->getResponse()->getContent();

        self::assertEquals('{"data":{"conflictingValidatorNamespaces":true}}', $response);

        // Validate definition file
        $defenitionFile = file_get_contents(self::$kernel->getCacheDir().'/overblog/graphql-bundle/__definitions__/MutationType.php');

        self::assertStringContainsString(
            'use Symfony\Component\Validator\Constraints as SymfonyConstraints;',
            $defenitionFile,
            'Generated definition file should contain import of Symfony\Component\Validator\Constraints aliased as SymfonyConstraints'
        );
        self::assertStringNotContainsString(
            'use '.Validator\CustomValidator1\Constraint::class,
            $defenitionFile,
            'Generated definition file should not contain imports of custom constraints, FQCN should be used instead'
        );
        self::assertStringNotContainsString(
            'use '.Validator\CustomValidator2\Constraint::class,
            $defenitionFile,
            'Generated definition file should not contain imports of custom constraints, FQCN should be used instead'
        );

        self::assertTrue(true);
    }
}
