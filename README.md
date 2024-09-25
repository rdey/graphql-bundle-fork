RedeyeGraphQLBundle
======================

![CI](https://github.com/redeye/GraphQLBundle/workflows/CI/badge.svg?branch=0.14)
[![Build status](https://ci.appveyor.com/api/projects/status/7ksxlcgwt40q74hv/branch/0.14?svg=true)](https://ci.appveyor.com/project/redeye/graphqlbundle/branch/0.14)
[![Coverage Status](https://coveralls.io/repos/github/redeye/GraphQLBundle/badge.svg?branch=0.14)](https://coveralls.io/github/redeye/GraphQLBundle?branch=0.14)
[![Latest Stable Version](https://poser.pugx.org/redeye/graphql-bundle/version)](https://packagist.org/packages/redeye/graphql-bundle)
[![Latest Unstable Version](https://poser.pugx.org/redeye/graphql-bundle/v/unstable)](https://packagist.org/packages/redeye/graphql-bundle)
[![Total Downloads](https://poser.pugx.org/redeye/graphql-bundle/downloads)](https://packagist.org/packages/redeye/graphql-bundle)

This is a fork of [overblog/graphql-bundle](https://github.com/overblog/graphql-bundle). For now, you're probably better off using that.

This Symfony bundle provides integration of [GraphQL](https://facebook.github.io/graphql/) using [webonyx/graphql-php](https://github.com/webonyx/graphql-php)
and [GraphQL Relay](https://facebook.github.io/relay/docs/en/graphql-server-specification.html).
It also supports:
* batching with [ReactRelayNetworkLayer](https://github.com/nodkz/react-relay-network-layer)
* batching with [Apollo GraphQL](https://www.apollographql.com/docs/react/api/link/apollo-link-batch-http/)
* upload and batching upload with [apollo-upload-client](https://github.com/jaydenseric/apollo-upload-client)

Upgrading from overblog/graphql-bundle
--------------------------------------

1. Change the dependencies in composer.json (it replaces overblog/graphql-bundle, overblog/dataloader-php, overblog/dataloader-bundle, redeye/graphql-federation-bundle and some aspects of redeye/graphql-extras-bundle)
2. Change bundle classes in `bundles.php`
3. Change config key from `overblog_graphql` to `redeye_graphql` in your config file. (restore config files if removed by composer)
4. If using Federation, set `federation: true` in your config
5. Change the bundle reference in your routing config
6. Do a search across your project for `Overblog\PromiseAdapter` and replace with `Redeye\GraphQLBundle\PromiseAdapter`
7. Do a search across your project for `Overblog\DataLoader` and replace with `Redeye\GraphQLBundle\DataLoader`
8. Do a search across your project for `Redeye\GraphqlExtrasBundle\DataLoader` and replace with `Redeye\GraphQLBundle\DataLoader`
9. Do a search across your project for use `Apollo\Federation` and replace with `Redeye\GraphQLBundle\Federation`
10. Do a search across your project for `Overblog` (case insensitive). In most cases you just need to replace these with `Redeye` (same case)


Proof of Concept
-----------------

* [mcg-web/graphql-symfony-doctrine-sandbox](https://github.com/mcg-web/graphql-symfony-doctrine-sandbox)
* [michaelperrin/blog-graphql-upload-demo](https://github.com/michaelperrin/blog-graphql-upload-demo)
* [redeye/GraphQLBundleDemo](https://github.com/redeye/GraphQLBundleDemo)
* [Samffy/graphql-poc](https://github.com/Samffy/graphql-poc)

Documentation
-------------

- [Quick start](docs/definitions/quick-start.md)
- [Installation](docs/index.md)
- [Definitions](docs/definitions/index.md)
  - [Type System](docs/definitions/type-system/index.md)
    - [Scalars](docs/definitions/type-system/scalars.md)
    - [Object](docs/definitions/type-system/object.md)
    - [Interface](docs/definitions/type-system/interface.md)
    - [Union](docs/definitions/type-system/union.md)
    - [Enum](docs/definitions/type-system/enum.md)
    - [Input Object](docs/definitions/type-system/input-object.md)
    - [Lists](docs/definitions/type-system/lists.md)
    - [Non-Null](docs/definitions/type-system/non-null.md)
  - [Type Inheritance](docs/definitions/type-inheritance.md)
  - [GraphQL schema language](docs/definitions/graphql-schema-language.md)
  - [Schema](docs/definitions/schema.md)
  - [Resolver](docs/definitions/resolver.md)
  - [Experimental coroutine executor](docs/definitions/coroutine-executor.md)
  - [Solving N+1 problem](docs/definitions/solving-n-plus-1-problem.md)
  - [Mutation](docs/definitions/mutation.md)
  - [Relay](docs/definitions/relay/index.md)
    - [Connection](docs/definitions/relay/connection.md)
      - [Relay Pagination helper](docs/helpers/relay-paginator.md)
    - [Node](docs/definitions/relay/node/index.md)
      - [Node](docs/definitions/relay/node/node.md)
      - [Plural](docs/definitions/relay/node/plural.md)
      - [Global id](docs/definitions/relay/node/global-id.md)
    - [Mutation](docs/definitions/relay/mutation.md)
  - [Builders](docs/definitions/builders/index.md)
    - [Field Builder](docs/definitions/builders/field.md)
    - [Fields Builder](docs/definitions/builders/fields.md)
    - [Args Builder](docs/definitions/builders/args.md)
  - [Expression language](docs/definitions/expression-language.md)
  - [Debug](docs/definitions/debug/index.md)
  - [GraphiQL](docs/definitions/graphiql/index.md)
  - [Upload files](docs/definitions/upload-files.md)
- [Data fetching](docs/data-fetching/index.md)
  - [Query batching](docs/data-fetching/batching.md)
  - [Promise](docs/data-fetching/promise.md)
- [Annotations & PHP 8 Attributes](docs/annotations/index.md)
- [Validation](docs/validation/index.md)
- [Security](docs/security/index.md)
  - [Handle CORS](docs/security/handle-cors.md)
  - [Object access control](docs/security/object-access-control.md)
  - [Fields access control](docs/security/fields-access-control.md)
  - [Fields public control](docs/security/fields-public-control.md)
  - [Limiting query depth](docs/security/limiting-query-depth.md)
  - [Query complexity analysis](docs/security/query-complexity-analysis.md)
  - [Disable introspection](docs/security/disable_introspection.md)
- [Errors handling](docs/error-handling/index.md)
- [Events](docs/events/index.md)
- [Profiler](docs/profiler/index.md)

Talks and slides to help you start
----------------------------------

* GraphQL in Symfony *by Bernd Alter* - [Twitter](https://twitter.com/bazoo0815)
  - [Talk about GraphQL and its implementation with Symfony (26.04.2017)](https://www.slideshare.net/berndalter7/graphql-in-symfony) `English`
* GraphQL is right in front of us, let's do it! *by Renato Mendes Figueiredo* - [Twitter](https://twitter.com/renatomefi), [GitHub](https://github.com/renatomefi)
  - [Slides at http://talks.mefi.in/graphql-scotphp17](http://talks.mefi.in/graphql-scotphp17/) `English`
  - [Video at SymfonyCamp UA 2017](https://www.youtube.com/watch?v=jyoYlnCPNgk) `English`
  - [Video at DPC 2017](https://www.youtube.com/watch?v=E7MjoCOGSSY) `English`
* A GraphQL API: From hype to production *by Aurélien David* - [Twitter](https://twitter.com/spyl94), [GitHub](https://github.com/spyl94)
  - [Slides at https://cap-collectif.slides.com/spyl/web2day-2019](https://cap-collectif.slides.com/spyl/web2day-2019) `English`
* Une API GraphQL: du hype à la prod *by Aurélien David* - [Twitter](https://twitter.com/spyl94), [GitHub](https://github.com/spyl94)
  - [Video at PHPTour 2017 Nantes](https://www.youtube.com/watch?v=xbipW6fgD6c) `French`
* Introduction to Symfony Flex and setting up RedeyeGraphQLBundle with it *by Renato Mendes Figueiredo* - [Twitter](https://twitter.com/renatomefi), [GitHub](https://github.com/renatomefi)
  - [Slides at http://talks.mefi.in/symfony-flex-101-symfonycampua](http://talks.mefi.in/symfony-flex-101-symfonycampua/) `English`
  - [Video at Symfony Camp UA 2017](https://www.youtube.com/watch?v=lWweoiCI9Hk) `English`

Community
---------

* Get support on [Symfony devs Slack](https://symfony.com/slack-invite)
  on the dedicated channel **redeye-graphql**.
* Get support in Telegram group [Redeye GraphQL](https://t.me/redeye_graphql)
* Follow us on [GitHub](https://github.com/redeye)

Contributing
------------

* [See contributing documentation](CONTRIBUTING.md)
* [Thanks to all contributors](https://github.com/redeye/GraphQLBundle/graphs/contributors)
