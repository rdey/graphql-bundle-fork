`apollo-ftv1.b64`
=================

A real `extensions.ftv1` payload, emitted by a real Apollo Server subgraph running its own
`ApolloServerPluginInlineTrace`. `TraceSerializationTest::testDecodesGoldenApolloPayload()`
parses it with *our* generated protobuf classes; if our field numbers or wire types ever drift
from Apollo's `reports.proto`, that test fails.

This is the only test in the suite that proves interoperability rather than self-consistency.

How it was produced
-------------------

```bash
npm install @apollo/server @apollo/subgraph graphql graphql-tag
```

```js
// gen.mjs
import { ApolloServer } from '@apollo/server';
import { startStandaloneServer } from '@apollo/server/standalone';
import { buildSubgraphSchema } from '@apollo/subgraph';
import gql from 'graphql-tag';

const typeDefs = gql`
  type Query { me: User! }
  type User @key(fields: "id") { id: ID!  name: String }
`;

const resolvers = {
  Query: {
    me: async () => { await new Promise(r => setTimeout(r, 5)); return { id: '1', name: 'Ada' }; },
  },
  User: {
    name: async (u) => { await new Promise(r => setTimeout(r, 3)); return u.name; },
  },
};

const server = new ApolloServer({ schema: buildSubgraphSchema({ typeDefs, resolvers }) });
const { url } = await startStandaloneServer(server, { listen: { port: 4999 } });

const res = await fetch(url, {
  method: 'POST',
  headers: {
    'content-type': 'application/json',
    'apollo-federation-include-trace': 'ftv1',
  },
  body: JSON.stringify({ query: '{ me { name } }' }),
});
console.log((await res.json()).extensions.ftv1);
await server.stop();
process.exit(0);
```

```bash
node gen.mjs | grep -E '^[A-Za-z0-9+/]+=*$' > apollo-ftv1.b64
```

(The `grep` drops Apollo's "Enabling inline tracing for this subgraph" log line, which it writes
to stdout alongside the payload.)

The resolvers sleep so that the recorded `start_time`/`end_time` are non-zero and ordered, which
is what the test asserts on.

Inspecting it
-------------

```bash
base64 -d < apollo-ftv1.b64 | protoc --decode_raw
```

`--decode_raw` needs no schema, so it shows the raw field numbers independently of anything in
this repository:

```
3  { 1: 1787060485  2: 614000000 }   # end_time   (google.protobuf.Timestamp)
4  { 1: 1787060485  2: 600000000 }   # start_time (google.protobuf.Timestamp)
11: 13847833                         # duration_ns
14 {                                 # root
  12 {                               #   child
    1: "me"                          #     response_name
    3: "User!"                       #     type
    8: 3513708                       #     start_time
    9: 10048833                      #     end_time
    12 { 1: "name"  3: "String"  8: 10184875  9: 13630958  13: "User" }
    13: "Query"                      #     parent_type
  }
}
31: 0x3ff0000000000000               # field_execution_weight == 1.0
```

Note that `--decode_raw` renders `type: "User!"` as a nested message `3 { 10: 0x21726573 }`,
because those five bytes also parse as a valid submessage. That is an ambiguity in `--decode_raw`
(which has no schema to disambiguate with), not a problem with the payload.
