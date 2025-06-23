# AliceGenerator

[![Packagist Version](https://img.shields.io/packagist/v/solitus0/alice-generator.svg)](https://packagist.org/packages/solitus0/alice-generator) [![License](https://img.shields.io/github/license/solitus0/AliceGenerator.svg?style=flat-square)](LICENSE)

AliceGenerator is a PHP library that generates realistic Alice fixtures from your existing database.
It integrates seamlessly with Doctrine and provides flexible customization options for naming strategies,
object handlers, and generation contexts.

> **Note**: This library is a **hard fork** of [trappar/AliceGenerator](https://github.com/trappar/AliceGenerator),
redesigned and maintained independently by [solitus0](https://github.com/solitus0).

## Documentation

For a full guide, see the [documentation](docs/index.md).

## Features

- Native Doctrine integration via `DoctrineMetadataHandler`
- Customizable property namers and reference namers
- Pluggable object handlers for custom types
- Fine-grained control over recursion and collection handling

## License

This project is licensed under the MIT License - see [LICENSE](LICENSE) for details.
