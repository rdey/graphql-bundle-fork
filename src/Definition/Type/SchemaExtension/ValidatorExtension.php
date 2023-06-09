<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Definition\Type\SchemaExtension;

use GraphQL\Type\Schema;

final class ValidatorExtension implements SchemaExtensionInterface
{
    public function process(Schema $schema): void
    {
        $schema->assertValid();
    }
}
