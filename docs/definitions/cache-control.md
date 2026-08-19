# HTTP caching with `@cacheControl` and `@cacheTag`

This bundle can turn cache hints written in your schema into a `Cache-Control` response header,
with the same semantics as Apollo Server, and pass `@cacheTag` through to the SDL your subgraph
publishes so an Apollo Router can use it for invalidation.

The two directives are handled by different parties:

| Directive | Interpreted by | Effect |
| --- | --- | --- |
| `@cacheControl` | this subgraph | produces the `Cache-Control` response header |
| `@cacheTag` | the router | tags data for targeted invalidation; the subgraph only publishes it |

## Enabling it

The feature is **off by default**, because switching it on gives a `Cache-Control` header to
responses that previously had none — including `no-store` for anything unannotated.

```yaml
redeye_graphql:
    cache_control:
        enabled: true
        default_max_age: 0
        calculate_http_headers: always   # always | if-cacheable | never
```

- **`default_max_age`** — the `maxAge` given to root fields and to fields returning a composite
  type when they carry no hint. `0`, as in Apollo, means "uncacheable unless you say otherwise".
- **`calculate_http_headers`** — `always` writes `no-store` when the response is not cacheable;
  `if-cacheable` leaves the header untouched in that case; `never` never writes the header at all.
  `true` and `false` are accepted as aliases for `always` and `never`.

## Writing the directives

In the GraphQL schema language:

```graphql
type Query {
    posts: [Post!]! @cacheControl(maxAge: 60) @cacheTag(format: "posts")
    me: User @cacheControl(maxAge: 30, scope: PRIVATE)
}

type Post @cacheControl(maxAge: 120) @cacheTag(format: "post-{$key.id}") @cacheTag(format: "post") {
    id: ID!
    title: String!
    author: Author @cacheControl(inheritMaxAge: true)
}
```

Or in YAML:

```yaml
Post:
    type: object
    config:
        cacheControl: { maxAge: 120 }
        cacheTags: ['post-{$key.id}', 'post']
        fields:
            title:
                type: "String!"
            author:
                type: Author
                cacheControl: { inheritMaxAge: true }
```

`@cacheControl` is valid on a field, an object, an interface and a union. `@cacheTag` is valid on a
field and an object, and is repeatable. Neither is available through PHP attributes or annotations.

> You do not need to declare `directive @cacheControl(...)` or `enum CacheControlScope` yourself.
> The bundle supplies both. Declaring the enum in a mapped `.graphql` file would create an ordinary
> type of that name and collide with the one the bundle emits.

## How the policy is computed

For each field that actually gets resolved:

1. If the field's declared return type is an object, interface or union, its `@cacheControl` is
   read first. This uses the **declared** type, so an interface- or union-typed field takes the
   hint from the interface or union rather than from whichever concrete type is returned.
2. The field's own `@cacheControl` is applied on top, overriding only the arguments it actually
   sets. `@cacheControl(scope: PRIVATE)` on a field keeps the `maxAge` declared on its return type.
3. If `maxAge` is still undecided and the field is either a root field or returns a composite type,
   it becomes `default_max_age`.

The response's `maxAge` is the smallest of every field that decided one, and its scope is `private`
if any of them said so. A field that never decides a `maxAge` contributes nothing — which is
precisely how inheritance works: non-root scalar fields stay undecided, so the value their parent
decided still stands.

`inheritMaxAge: true` extends that to a field or type that would otherwise be defaulted. It cannot
be combined with an explicit `maxAge`; there would be nothing left to inherit.

Root fields are always defaulted, `inheritMaxAge` or not — there is no parent to inherit from.

### When no header is written

- A response carrying **errors** is always `no-store`, even if every field it did resolve was
  cacheable.
- A **mutation** needs no special handling: its root fields are defaulted like any other, so they
  come out uncacheable unless annotated.

### The `_entities` field

`Query._entities` is a root field, so the rules above would pin every federated entity fetch at
`maxAge: 0` and no entity response could ever be cached. It is therefore exempt from the root
default, and instead takes the hint from the **concrete type** of each representation it is asked
for — matching Apollo. Annotate the entity type itself:

```graphql
type Post @key(fields: "id") @cacheControl(maxAge: 120) { ... }
```

If you replace the `_entities` resolver with your own, that lookup no longer happens and `_entities`
contributes no hint at all, so those responses come out uncacheable rather than wrongly cacheable.

## Batched requests

The `/batch` endpoint runs several operations in one HTTP response, which can carry only one header,
so the aggregate is the most restrictive of them: the smallest `maxAge`, `private` if any operation
was, and `no-store` if any of them errored. This is the same rule that already applies across the
fields of a single query.

Apollo Server instead writes no header at all for a batched response. The difference does not arise
behind a router, which never batches to its subgraphs.

## What lands in the header

```
Cache-Control: max-age=60, public
Cache-Control: max-age=30, private
Cache-Control: no-store, private
```

Symfony appends `, private` to any `Cache-Control` naming neither `public`, `private` nor
`s-maxage`, so the uncacheable case reads `no-store, private` rather than Apollo's bare `no-store`.
This is not reachable through Symfony's `Response` API and means the same thing to every cache.

## What the published SDL looks like

With `federation: true`, `_service { sdl }` and `redeye:graphql:dump-federated` emit:

```graphql
extend schema
  @link(url: "https://specs.apollo.dev/federation/v2.12",
        import: ["@key", "@provides", "@requires", "@external", "@shareable", "@override", "@cacheTag"])

enum CacheControlScope {
  PUBLIC
  PRIVATE
}

directive @cacheControl(maxAge: Int, scope: CacheControlScope, inheritMaxAge: Boolean) on FIELD_DEFINITION | OBJECT | INTERFACE | UNION

type Post @key(fields: "id") @cacheControl(maxAge: 120) @cacheTag(format: "post-{$key.id}") { ... }
```

`@cacheTag` arrives through the `@link` import — it is part of the federation spec from v2.12 — so
it gets no local definition. `@cacheControl` is not a federation directive, so it is declared
locally; composition drops it from the supergraph, which is correct, because the router learns the
TTL from the `Cache-Control` header rather than from the schema.

Composing this SDL emits one hint, that `CacheControlScope` is defined but unused. That is expected:
the enum exists only as an argument type of a directive the supergraph does not keep.
