<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Definition\Resolver;

use ReflectionClass;
use ReflectionMethod;
use Doctrine\Common\Annotations\AnnotationReader;
use Redeye\GraphQLBundle\Annotation\Alias;

trait AttributeAliasesTrait
{
    public static function getAliases(): array
    {
        $aliases = [];

        $reflectionClass = new ReflectionClass(static::class);
        $annotationReader = null;

        if (class_exists(AnnotationReader::class)) {
            $annotationReader = new AnnotationReader();
        }

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $annotation = null;

            if (method_exists($method, 'getAttributes')) {
                $attrs = $method->getAttributes(Alias::class);
                if (count($attrs) > 0) {
                    $annotation = $attrs[0]->newInstance();
                }
            } else if ($annotationReader) {
                $annotation = $annotationReader->getMethodAnnotation($method, Alias::class);
            }

            if ($annotation) {
                $aliases[$method->getName()] = $annotation->alias;
            }
        }

        return $aliases;
    }
}
