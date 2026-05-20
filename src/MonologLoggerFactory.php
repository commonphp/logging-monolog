<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog;

use CommonPHP\Drivers\Logging\Monolog\Exceptions\MonologConfigurationException;
use CommonPHP\Logging\Contracts\LogProcessorInterface as CommonPHPProcessorInterface;
use CommonPHP\Logging\Contracts\LogTargetInterface;
use CommonPHP\Logging\Enums\LogLevelValue;
use CommonPHP\Logging\LogManager;
use DateTimeZone;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Throwable;

final class MonologLoggerFactory
{
    public function __construct(
        private readonly MonologHandlerFactory $handlers = new MonologHandlerFactory(),
        private readonly MonologProcessorFactory $processors = new MonologProcessorFactory(),
    ) {
    }

    public function create(?MonologDriverOptions $options = null, ?Logger $logger = null): LogManager
    {
        return new LogManager($this->driver($options, $logger));
    }

    public function driver(?MonologDriverOptions $options = null, ?Logger $logger = null): MonologLogDriver
    {
        return new MonologLogDriver($logger, $options);
    }

    public function monolog(?MonologDriverOptions $options = null, ?Logger $logger = null): Logger
    {
        return $this->driver($options, $logger)->getLogger();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fromArray(array $config): LogManager
    {
        return $this->create($this->optionsFromArray($config));
    }

    /**
     * @param array<string, mixed> $config
     */
    public function driverFromArray(array $config): MonologLogDriver
    {
        return $this->driver($this->optionsFromArray($config));
    }

    /**
     * @param array<string, mixed> $config
     */
    public function optionsFromArray(array $config): MonologDriverOptions
    {
        return new MonologDriverOptions(
            channel: (string) ($config['channel'] ?? 'app'),
            handlers: $this->resolveHandlers($config['handlers'] ?? []),
            processors: $this->resolveProcessors($config['processors'] ?? []),
            includePsrLogMessageProcessor: (bool) ($config['include_psr_log_message_processor'] ?? true),
            timezone: $this->resolveTimezone($config['timezone'] ?? null),
            useMicrosecondTimestamps: (bool) ($config['use_microsecond_timestamps'] ?? true),
            detectCycles: (bool) ($config['detect_cycles'] ?? true),
            useErrorLogFallback: (bool) ($config['use_error_log_fallback'] ?? true),
        );
    }

    public function file(
        string $path,
        mixed $level = LogLevelValue::Debug,
        string $channel = 'app',
        ?iterable $processors = null,
    ): LogManager {
        return $this->create(new MonologDriverOptions(
            channel: $channel,
            handlers: [$this->handlers->file($path, $level)],
            processors: $this->resolveProcessors($processors ?? []),
        ));
    }

    public function rotatingFile(
        string $path,
        int $maxFiles = 0,
        mixed $level = LogLevelValue::Debug,
        string $channel = 'app',
        ?iterable $processors = null,
    ): LogManager {
        return $this->create(new MonologDriverOptions(
            channel: $channel,
            handlers: [$this->handlers->rotatingFile($path, $maxFiles, $level)],
            processors: $this->resolveProcessors($processors ?? []),
        ));
    }

    public function stdout(
        mixed $level = LogLevelValue::Debug,
        string $channel = 'app',
        ?iterable $processors = null,
    ): LogManager {
        return $this->create(new MonologDriverOptions(
            channel: $channel,
            handlers: [$this->handlers->stdout($level)],
            processors: $this->resolveProcessors($processors ?? []),
        ));
    }

    public function stderr(
        mixed $level = LogLevelValue::Debug,
        string $channel = 'app',
        ?iterable $processors = null,
    ): LogManager {
        return $this->create(new MonologDriverOptions(
            channel: $channel,
            handlers: [$this->handlers->stderr($level)],
            processors: $this->resolveProcessors($processors ?? []),
        ));
    }

    public function errorLog(
        mixed $level = LogLevelValue::Debug,
        string $channel = 'app',
        ?iterable $processors = null,
    ): LogManager {
        return $this->create(new MonologDriverOptions(
            channel: $channel,
            handlers: [$this->handlers->errorLog($level)],
            processors: $this->resolveProcessors($processors ?? []),
        ));
    }

    public function null(string $channel = 'app'): LogManager
    {
        return $this->create(new MonologDriverOptions(
            channel: $channel,
            handlers: [$this->handlers->null()],
            includePsrLogMessageProcessor: false,
            useErrorLogFallback: false,
        ));
    }

    /**
     * @return list<HandlerInterface>
     */
    private function resolveHandlers(mixed $handlers): array
    {
        if (!is_array($handlers)) {
            throw new MonologConfigurationException('Monolog handlers configuration must be an array.');
        }

        $resolved = [];

        foreach ($handlers as $handler) {
            if ($handler instanceof HandlerInterface) {
                $resolved[] = $handler;

                continue;
            }

            if ($handler instanceof LogTargetInterface) {
                $resolved[] = $this->handlers->target($handler);

                continue;
            }

            if (is_array($handler)) {
                $resolved[] = $this->handlers->fromArray($handler);

                continue;
            }

            throw new MonologConfigurationException(
                'Each Monolog handler must be a handler instance, CommonPHP target, or handler config array.',
            );
        }

        return $resolved;
    }

    /**
     * @return list<ProcessorInterface|callable>
     */
    private function resolveProcessors(mixed $processors): array
    {
        if (!is_iterable($processors)) {
            throw new MonologConfigurationException('Monolog processors configuration must be iterable.');
        }

        $resolved = [];

        foreach ($processors as $processor) {
            if ($processor instanceof CommonPHPProcessorInterface) {
                $resolved[] = $this->processors->commonPHP($processor);

                continue;
            }

            if ($processor instanceof ProcessorInterface || is_callable($processor)) {
                $resolved[] = $processor;

                continue;
            }

            if (is_array($processor)) {
                $resolved[] = $this->processors->fromArray($processor);

                continue;
            }

            throw new MonologConfigurationException(
                'Each Monolog processor must be callable, a processor instance, or a processor config array.',
            );
        }

        return $resolved;
    }

    private function resolveTimezone(mixed $timezone): ?DateTimeZone
    {
        if ($timezone === null || $timezone instanceof DateTimeZone) {
            return $timezone;
        }

        if (is_string($timezone) && $timezone !== '') {
            try {
                return new DateTimeZone($timezone);
            } catch (Throwable $exception) {
                throw new MonologConfigurationException(
                    'Invalid Monolog timezone "' . $timezone . '".',
                    $exception->getCode(),
                    $exception,
                );
            }
        }

        throw new MonologConfigurationException('Monolog timezone must be a DateTimeZone, timezone string, or null.');
    }
}
