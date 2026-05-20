# Usage

The Monolog driver implements the CommonPHP logging driver contract while keeping Monolog handlers, processors, and formatters available to applications.

## Basic file logger

```php
<?php

use CommonPHP\Drivers\Logging\Monolog\MonologLoggerFactory;

$logger = (new MonologLoggerFactory())->file(__DIR__ . '/var/app.log');

$logger->warning('Queue {queue} is backing up.', ['queue' => 'default']);
```

## Manual driver setup

```php
<?php

use CommonPHP\Drivers\Logging\Monolog\MonologDriverOptions;
use CommonPHP\Drivers\Logging\Monolog\MonologHandlerFactory;
use CommonPHP\Drivers\Logging\Monolog\MonologLogDriver;
use CommonPHP\Logging\LogManager;

$handlers = new MonologHandlerFactory();
$driver = new MonologLogDriver(options: new MonologDriverOptions(
    channel: 'worker',
    handlers: [
        $handlers->rotatingFile(__DIR__ . '/var/worker.log', maxFiles: 14),
    ],
));

$logger = new LogManager($driver);
```

CommonPHP targets and processors can still be added through `LogManager::addTarget()` and `LogManager::pushProcessor()`. The driver adapts them to Monolog records before handlers run.
