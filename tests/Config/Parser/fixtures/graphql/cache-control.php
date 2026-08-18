<?php

declare(strict_types=1);

return [
    'Query' => [
        'type' => 'object',
        'config' => [
            'description' => null,
            'fields' => [
                'posts' => [
                    'type' => '[Post!]!',
                    'description' => null,
                    'cacheControl' => ['maxAge' => 60],
                    'cacheTags' => ['posts'],
                ],
                // Arguments are matched by name, so declaration order is irrelevant.
                'me' => [
                    'type' => 'User',
                    'description' => null,
                    'cacheControl' => ['maxAge' => 10, 'scope' => 'PRIVATE'],
                ],
                // Directives after @deprecated must survive.
                'legacy' => [
                    'type' => 'String',
                    'description' => null,
                    'deprecationReason' => 'Use posts instead',
                    'cacheControl' => ['maxAge' => 5],
                ],
                // A quoted enum value is accepted, since tooling emits it both ways.
                'quoted' => [
                    'type' => 'String',
                    'description' => null,
                    'cacheControl' => ['scope' => 'PRIVATE'],
                ],
            ],
        ],
    ],
    'Post' => [
        'type' => 'object',
        'config' => [
            'description' => null,
            'fields' => [
                'id' => [
                    'type' => 'ID!',
                    'description' => null,
                ],
                'title' => [
                    'type' => 'String!',
                    'description' => null,
                ],
                'author' => [
                    'type' => 'User',
                    'description' => null,
                    'cacheControl' => ['inheritMaxAge' => true],
                ],
            ],
            'cacheControl' => ['maxAge' => 120],
            // @cacheTag is repeatable.
            'cacheTags' => ['post-{$key.id}', 'post'],
        ],
    ],
    'User' => [
        'type' => 'object',
        'config' => [
            'description' => null,
            'fields' => [
                'id' => [
                    'type' => 'ID!',
                    'description' => null,
                ],
                'name' => [
                    'type' => 'String!',
                    'description' => null,
                ],
            ],
            'cacheControl' => ['scope' => 'PRIVATE', 'inheritMaxAge' => true],
        ],
    ],
    'Node' => [
        'type' => 'interface',
        'config' => [
            'description' => null,
            'fields' => [
                'id' => [
                    'type' => 'ID!',
                    'description' => null,
                ],
            ],
            'cacheControl' => ['maxAge' => 30],
        ],
    ],
    'SearchResult' => [
        'type' => 'union',
        'config' => [
            'description' => null,
            'types' => ['Post', 'User'],
            'cacheControl' => ['maxAge' => 15],
        ],
    ],
];
