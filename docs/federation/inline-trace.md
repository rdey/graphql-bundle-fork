Apollo Federation inline tracing (FTV1)
=======================================

Apollo's gateway/router collects per-field performance data from subgraphs by sending the HTTP
header `apollo-federation-include-trace: ftv1`. A compliant subgraph answers with an
`extensions.ftv1` entry containing a base64-encoded protobuf `Trace` message. Apollo Server
does this via `ApolloServerPluginInlineTrace`, installed by default on every subgraph;
`graphql-java` does the same via `FederatedTracingInstrumentation`.

This bundle implements the same behaviour.

Configuration
-------------

```yaml
redeye_graphql:
    federation: true
    # inline_trace is enabled by default when federation is true
```

Explicit forms:

```yaml
redeye_graphql:
    inline_trace: false                 # disable
    inline_trace: true                  # enable, regardless of `federation`
    inline_trace:
        include_errors: masked          # masked (default) | unmodified
        transformer_service: App\GraphQL\MyTraceErrorTransformer
```

| Key | Default | Meaning |
|---|---|---|
| `enabled` | inherits `redeye_graphql.federation` | Whether to emit traces at all. |
| `include_errors` | `masked` | Error reporting policy — see [Errors](#errors). |
| `transformer_service` | `null` | Service id implementing `Redeye\GraphQLBundle\Federation\Tracing\TraceErrorTransformerInterface`. Overrides `include_errors`. |

Note that setting only `include_errors` or `transformer_service` does **not** enable the
feature — `enabled` still inherits `federation`. Configuring options should not silently turn
tracing on for a non-federated schema.

### Security

With inline tracing enabled, **any** client that sends `apollo-federation-include-trace: ftv1`
receives field-level timings for your server, and — in `unmodified` mode — error detail.
Subgraphs are not meant to be publicly reachable. Apollo Server carries the same exposure and
the same caveat.

Specification
-------------

There is no published specification for FTV1. What follows is derived from Apollo's
`reports.proto` and the two reference implementations:

- [`apollo-server` / `plugin/inlineTrace/index.ts`](https://github.com/apollographql/apollo-server/blob/main/packages/server/src/plugin/inlineTrace/index.ts)
- [`apollo-server` / `plugin/traceTreeBuilder.ts`](https://github.com/apollographql/apollo-server/blob/main/packages/server/src/plugin/traceTreeBuilder.ts)
- [`apollo-server` / `reports.proto`](https://github.com/apollographql/apollo-server/blob/main/packages/usage-reporting-protobuf/src/reports.proto)
- [`federation-jvm` / `FederatedTracingInstrumentation.java`](https://github.com/apollographql/federation-jvm/blob/main/graphql-java-support/src/main/java/com/apollographql/federation/graphqljava/tracing/FederatedTracingInstrumentation.java)
- [Apollo docs: Inline trace plugin](https://www.apollographql.com/docs/apollo-server/api/plugin/inline-trace)

### Trigger

A request carrying the HTTP header `apollo-federation-include-trace` with the exact value
`ftv1` requests a trace. Any other value, or absence, means no trace. Header-name matching is
case-insensitive (HTTP); the value comparison is strict — Apollo only ever sends `ftv1`.

### Response

The response `extensions` object gains a key `ftv1` whose value is
`base64(Trace.serializeToString())`. Nothing else about the response changes. If the request did
not ask for a trace, `extensions.ftv1` is absent.

### Wire format

Subset of Apollo's `reports.proto`. **Field numbers are normative and must match upstream
exactly.** The vendored copy lives at [`proto/reports.proto`](../../proto/reports.proto).

```proto
message Trace {
  google.protobuf.Timestamp end_time    = 3;   // wallclock, trace end
  google.protobuf.Timestamp start_time  = 4;   // wallclock, trace start
  uint64                    duration_ns = 11;  // monotonic; may != end-start
  Node                      root        = 14;
  double        field_execution_weight  = 31;  // always 1.0

  message Node {
    oneof id {
      string response_name = 1;   // response key (alias-aware) for field nodes
      uint32 index         = 2;   // list position for list-element nodes
    }
    string type                = 3;   // field return type, e.g. "String!"
    string parent_type         = 13;  // owning type name, e.g. "User"
    uint64 start_time          = 8;   // ns since Trace.start_time
    uint64 end_time            = 9;   // ns since Trace.start_time
    repeated Error error       = 11;
    repeated Node  child       = 12;
    string original_field_name = 14;  // set only when response_name is an alias
  }

  message Error {
    string            message  = 1;
    repeated Location location = 2;
    uint64            time_ns  = 3;   // not set
    string            json     = 4;
  }

  message Location {
    uint32 line   = 1;
    uint32 column = 2;
  }
}
```

`field_execution_weight` is set to `1.0`, matching Apollo Server. Studio scales field-usage
statistics by it.

Fields deliberately **not** emitted (gateway / usage-reporting concerns): `signature`,
`details`, `client_name`, `client_version`, `http`, `cache_policy`, `query_plan`,
`full_query_cache_hit`, `persisted_query_*`, `registered_operation`, `forbidden_operation`,
`is_incomplete`, and `Node.cache_policy`.

### Node tree

The tree mirrors the shape of the GraphQL **response**, not resolver invocation order.

- `Trace.root` has no `id`, no `type` and no timings. It exists only as parent of the
  operation's top-level selections.
- Each resolved field contributes one node keyed by its **response name** (the alias if
  aliased, otherwise the field name). When the response name differs from the schema field
  name, `original_field_name` carries the schema field name.
- Each element of a list contributes an intermediate node carrying only `index`. Index nodes
  have no `type`, `parent_type` or timings — they are structural, and no resolver ever fires
  for them.
- Ancestors are created on demand: inserting `["users", 0, "name"]` creates `users`, `0` and
  `name` as needed. Insertion is order-independent.
- `start_time`/`end_time` are nanoseconds **relative to `Trace.start_time`**, from a monotonic
  clock.

### Timing boundaries

`Trace.start_time` is taken before parsing and validation, so parse and validation cost is
inside `duration_ns`. `end_time`/`duration_ns` are taken after execution completes.
`duration_ns` uses `hrtime(true)` (monotonic); `start_time`/`end_time` use wallclock
(`microtime(true)`) converted to `google.protobuf.Timestamp`.

A trace is emitted even when the operation fails to parse or validate — the tree is then just
the root node carrying the errors.

### Errors

Errors attach to the node matching the error's `path`; errors with no path (parse, validation,
variable coercion) go on the root node. If no node exists for a path, it is created along with
its ancestors.

Each `Trace.Error` carries `message` (after the policy below), `location` (1-based
line/column), and `json` (the standard `{message, locations, path, extensions}` serialization
of the policy-applied error). `time_ns` is not set — neither reference implementation sets it.

| `include_errors` | Behaviour |
|---|---|
| `masked` (default) | `message` becomes the literal `<masked>`; `extensions` becomes `{"maskedBy": "RedeyeGraphQLBundleInlineTrace"}`. `locations` and `path` are preserved. |
| `unmodified` | The **client-visible** message and extensions are reported. |
| `transformer_service` | The service receives each error and returns a replacement or `null`; `null` drops the error from the trace. Only `message` and `extensions` may change — `locations` and `path` always come from the original so the error stays on the right node. |

Masking is the default because a trace crosses a trust boundary: it leaves the subgraph and is
stored by Apollo Studio, and subgraph errors routinely carry internal detail.

> **`unmodified` does not mean "raw".** This bundle's error handler never rewrites the message
> on the `GraphQL\Error\Error` object — masking happens later, inside the response formatter.
> So `$error->getMessage()` is still the raw PHP exception text (SQL, DSNs, file paths) while
> the trace is being built. `unmodified` therefore reports what the *client* would see, via
> `FormattedError::createFromException($error, DebugFlag::NONE, $internalErrorMessage)`, not the
> raw exception message.

A custom transformer:

```php
namespace App\GraphQL;

use GraphQL\Error\Error;
use Redeye\GraphQLBundle\Federation\Tracing\TraceErrorTransformerInterface;

final class MyTraceErrorTransformer implements TraceErrorTransformerInterface
{
    public function transform(Error $error): ?Error
    {
        // Drop anything that might carry customer data.
        if (str_contains($error->getMessage(), '@')) {
            return null;
        }

        return $error;
    }
}
```

Implementation notes
--------------------

The trace is built at three points, mirroring Apollo Server's plugin lifecycle:

| Apollo Server | This bundle |
|---|---|
| `requestDidStart` (header check, `startTiming`) | `InlineTraceListener::onPreExecutor` on `graphql.pre_executor` |
| `willResolveField` | `TracingReferenceExecutor::resolveFieldValueOrError()` |
| `didEncounterErrors` + `willSendResponse` | `InlineTraceListener::onPostExecutor` on `graphql.post_executor`, priority 100 |

The in-progress trace is carried in the per-execution `ArrayObject` context under the key
`Redeye\GraphQLBundle\Federation\Tracing\InlineTraceListener::CONTEXT_KEY`. It is visible to
resolvers as `$context[...]` but is **internal** — do not rely on it. Keeping it there (rather
than on the listener service) is what makes batched requests correct: `Request\Executor` builds
a fresh context per query, so each query in a batch gets its own independent trace.

Per-field timings are collected by `TracingReferenceExecutor`, which this bundle installs
process-wide via `GraphQL\Executor\Executor::setImplementationFactory()`. **If your application
calls `setImplementationFactory()` itself, field nodes will silently disappear** while the trace
envelope is still emitted.

Very large responses produce very large traces. The builder caps the node count (25 000) and
stops recording beyond it; a truncated trace is preferable to exhausting memory.

Verifying by hand
-----------------

```bash
curl -s localhost:8000/graphql \
  -H 'content-type: application/json' \
  -H 'apollo-federation-include-trace: ftv1' \
  -d '{"query":"{ me { name } }"}' | jq -r .extensions.ftv1 | base64 -d | protoc --decode_raw
```

`protoc --decode_raw` needs no schema and prints the field-number tree directly, so it
independently confirms field numbering against the table above.

Apollo's [`apollo-federation-subgraph-compatibility`](https://github.com/apollographql/apollo-federation-subgraph-compatibility)
harness asserts that a subgraph returns `extensions.ftv1` for a header-carrying request, and is
the strongest available end-to-end check.
