# Testing

## Required dev dependencies

This package uses PHPUnit 13 for its test suite. `composer.json` already lists:

- `phpunit/phpunit:^13.1`

If PHPUnit is missing from a clone, install it with:

```bash
composer require --dev phpunit/phpunit:^13.1
```

## Running tests

Install dependencies for this repository, then run PHPUnit from this package root:

```bash
composer install
vendor/bin/phpunit -c phpunit.xml.dist
```

On Windows, use `vendor\bin\phpunit.bat`.

## Notes

The unit suite covers the driver contract, factory-created handlers/processors, PSR-3 level handling, and the adapters that bridge CommonPHP targets and processors into Monolog.
