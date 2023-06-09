Schema
======

Default files location
-------

**Symfony Flex:**

- ***Main configuration:*** `/config/packages/graphql.yaml`
- ***Types:*** `/config/graphql/types/query.yaml`
- ***Routes:*** `/config/routes/graphql.yaml`

**Symfony Standard:**

- ***Main configuration:*** `/app/config/config.yml`
- ***Types:*** `src/MyBundle/Resources/config/graphql/Query.types.yml`
- ***Routes:*** `/app/config/routing.yml`

Yaml configuration
-------

For more examples on what can be done with Symfony Expression Language (the stuff after `@=`), check
[here](expression-language.md) and [here](http://symfony.com/doc/current/components/expression_language/syntax.html).

```yaml
# This is the type that will be the root of our query, and the
# entry point into our schema. It gives us the ability to fetch
# objects by their IDs, as well as to fetch the undisputed hero
# of the Star Wars trilogy, R2-D2, directly.
#
# This implements the following type system shorthand:
#   type Query {
#     hero(episode: Episode): Character
#     human(id: String!): Human
#     droid(id: String!): Droid
#   }
#
Query:
    type: object
    config:
        description: "A humanoid creature in the Star Wars universe."
        fields:
            hero:
                type: "Character"
                args:
                    episode:
                        description: "If omitted, returns the hero of the whole saga. If provided, returns the hero of that particular episode."
                        type: "Episode"
                resolve: "@=query('character_hero', args['episode'].getId())"
            human:
                type: "Human"
                args:
                    id:
                        description: "id of the human"
                        type: "String!"
                resolve: "@=query('character_human', args['id'])"
            droid:
                type: "Droid"
                args:
                    id:
                        description: "id of the droid"
                        type: "String!"
                resolve: "@=query('character_droid', args)"
```

 
```yaml
redeye_graphql:
    definitions:
        schema:
            query: Query
            mutation: ~
            # the name of extra types that can not be detected
            # by graphql-php during static schema analysis.
            # These types names should be explicitly declare here
            types: []
```

## Batching


Batching can help decrease io between server and client.
The default route of batching is `/batch`.

## Multiple schema endpoint

```yaml
redeye_graphql:
    definitions:
        schema:
            foo:
                query: fooQuery
            bar:
                query: barQuery
                mutation: barMutation
```

**foo** schema endpoint can be access:

| type           | Path                 |
| -------------- | -------------------- |
| simple request | `/graphql/foo`       |
| batch request  | `/graphql/foo/batch` |
| GraphiQL*      | `/graphiql/foo`      |

**bar** schema endpoint can be access:

| type           | Path                 |
| -------------- | -------------------- |
| simple request | `/graphql/bar`       |
| batch request  | `/graphql/bar/batch` |
| GraphiQL*      | `/graphiql/bar`      |

### Default schema

The schema considered as the default is the one with the name `default` if it exists, otherwise, it will be the first one defined.  

\* `/graphiql` depends on [RedeyeGraphiQLBundle](https://github.com/redeye/GraphiQLBundle)
