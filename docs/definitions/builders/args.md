Args builder
============

Builder is a way to don't repeat args definition.

Define your custom args builder:

```yaml
#app/config/config.yml
redeye_graphql:
    #...
    definitions:
        #...
        builders:
            args:
                -
                    alias: "Pager"
                    class: "MyBundle\\GraphQL\\Args\\Pager"
#               using short syntax
#               Pager: "MyBundle\\GraphQL\\Args\\Pager"
```

Builder class must implement `Redeye\GraphQLBundle\Definition\Builder\MappingInterface`

```php
<?php

namespace MyBundle\GraphQL\Args;

use Redeye\GraphQLBundle\Definition\Builder\MappingInterface;

class Pager implements MappingInterface
{
    public function toMappingDefinition(array $config): array
    {
        $defaultLimit = isset($config['defaultLimit']) ? (int)$config['defaultLimit'] : 20;

        return [
            'limit' => [
                'type' => 'Int!',
                'defaultValue' => $defaultLimit,
            ],
            'offset' => [
                'type' => 'Int!',
                'defaultValue' => 0,
            ],
        ];
    }
}
```

usage:

```yaml
foo:
    type: "object"
    config:
        fields:
            categories:
                type: "[String!]!"
                argsBuilder: "Pager"

            categories2:
                type: "[String!]!"
                argsBuilder:
                    builder: "Pager"
                    config:
                        defaultLimit: 50
```

this is equivalent to:

```yaml
foo:
    type: "object"
    config:
        fields:
            categories:
                type: "[String!]!"
                args:
                    limit:
                        type: "Int!"
                        defaultValue: 20
                    offset:
                        type: "Int!"
                        defaultValue: 0
            categories2:
                type: "[String!]!"
                args:
                    limit:
                        type: "Int!"
                        defaultValue: 50
                    offset:
                        type: "Int!"
                        defaultValue: 0
```
