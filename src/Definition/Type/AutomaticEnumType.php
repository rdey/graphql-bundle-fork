<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Definition\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Utils\Utils;
use MyCLabs\Enum\Enum;
use Redeye\GraphQLBundle\Definition\ConfigProcessor;
use Redeye\GraphQLBundle\Definition\Resolver\AliasedInterface;

abstract class AutomaticEnumType extends EnumType implements AliasedInterface
{
    private bool $native = false;
    private bool $myclabs = false;

    /** @var class-string<\BackedEnum|Enum> */
    private string $enumClass;

    /**
     * @param class-string<\BackedEnum|Enum> $enumClass
     */
    public function __construct(string $enumClass, ConfigProcessor $configProcessor)
    {
        $this->enumClass = $enumClass;
        if (function_exists('enum_exists') && enum_exists($enumClass)) {
            $reflectionEnum = new \ReflectionEnum($enumClass);
            if ($reflectionEnum->isBacked()) {
                $this->native = true;
                $values = [];

                foreach ($enumClass::cases() as $case) {
                    $values[$case->name] = ['value' => $case->value];
                }
            }
        } elseif (class_exists(Enum::class) && is_subclass_of($enumClass, Enum::class, true)) {
            $this->myclabs = true;
            $values = [];

            foreach ($enumClass::values() as $case) {
                $values[$case->getValue()] = ['value' => $case];
            }
        } else {
            throw new \InvalidArgumentException(
                'Enum class must either be a native string-backed Enum, or a MyCLabs enum',
            );
        }

        $config = [
            'name' => static::getName(),
            'values' => $values,
        ];

        parent::__construct($configProcessor->process($config));
    }

    public function serialize($value)
    {
        if ($this->native && $value instanceof \BackedEnum) {
            return $value->value;
        }

        return (string) $value;
    }

    public function parseValue($value)
    {
        return $this->from($value);
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof EnumValueNode) {
            return $this->from($valueNode->value);
        }

        // Intentionally without message, as all information already in wrapped Exception
        throw new Error();
    }

    /**
     * {@inheritdoc}
     */
    public static function getAliases(): array
    {
        return [static::getName()];
    }

    abstract public static function getName(): string;

    /**
     * @return \BackedEnum|Enum
     */
    private function from(string $value): object
    {
        $className = $this->enumClass;
        $enum = null;
        if ($this->native) {
            /** @var class-string<\BackedEnum> $className */
            $enum = $className::tryFrom($value);
        } elseif ($this->myclabs) {
            /** @var class-string<Enum> $className */
            if ($className::isValid($value)) {
                $enum = $className::from($value);
            }
        }

        if (!$enum) {
            throw new Error('Cannot represent value as enum: ' . Utils::printSafe($value));
        }

        return $enum;
    }
}
