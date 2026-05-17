# CommonPHP Monolog Logging Driver

Logging driver for CommonPHP that wraps Monolog for configurable PSR-3 logging.

## Requirements

- PHP `^8.5`
- `comphp/logging:^0.3`
- `monolog/monolog`

## Installation

Once this package is available through your Composer repositories, install it with:

```bash
composer require comphp/logging-monolog
```

## Usage

```php
<?php

// TODO: Write usage
```

## Driver Notes

This driver is intended to let CommonPHP Logging use Monolog handlers, processors, formatters, and channels without making the core logging package depend directly on Monolog.

Applications may use this driver when they need production-ready logging targets, rotation, formatting, or integrations that Monolog already supports.

## Error Handling

Logger setup, handler, formatting, write, and configuration failures should throw CommonPHP logging driver exceptions when logging cannot be completed or configured safely.

## Documentation

- [Usage](docs/usage.md)
- [Testing](TESTING.md)
- [Contributing](CONTRIBUTING.md)
- [Security](SECURITY.md)

## License

MIT. See [LICENSE.md](LICENSE.md).
