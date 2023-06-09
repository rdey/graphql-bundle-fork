<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Federation;

use Redeye\GraphQLBundle\Federation\Directives\KeyDirective;
use Redeye\GraphQLBundle\Federation\Directives\ExternalDirective;
use Redeye\GraphQLBundle\Federation\Directives\OverrideDirective;
use Redeye\GraphQLBundle\Federation\Directives\ProvidesDirective;
use Redeye\GraphQLBundle\Federation\Directives\RequiresDirective;
use Redeye\GraphQLBundle\Federation\Directives\ShareableDirective;

/**
 * Helper class to get directives for annotating federated entity types.
 */
class Directives
{
    /** @var array */
    private static $directives;

    /**
     * Gets the @key directive
     */
    public static function key(): KeyDirective
    {
        return self::getDirectives()['key'];
    }

    /**
     * Gets the @external directive
     */
    public static function external(): ExternalDirective
    {
        return self::getDirectives()['external'];
    }

    /**
     * Gets the @requires directive
     */
    public static function requires(): RequiresDirective
    {
        return self::getDirectives()['requires'];
    }

    /**
     * Gets the @provides directive
     */
    public static function provides(): ProvidesDirective
    {
        return self::getDirectives()['provides'];
    }

    /**
     * Gets the @shareable directive
     */
    public static function sharable(): ShareableDirective
    {
        return self::getDirectives()['sharable'];
    }

    /**
     * Gets the @override directive
     */
    public static function override(): OverrideDirective
    {
        return self::getDirectives()['override'];
    }

    /**
     * Gets the directives that can be used on federated entity types
     */
    public static function getDirectives(): array
    {
        if (!self::$directives) {
            self::$directives = [
                'key' => new KeyDirective(),
                'external' => new ExternalDirective(),
                'requires' => new RequiresDirective(),
                'provides' => new ProvidesDirective(),
                'shareable' => new ShareableDirective(),
                'override' => new OverrideDirective(),
            ];
        }

        return self::$directives;
    }
}
