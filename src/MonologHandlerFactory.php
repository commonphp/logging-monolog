<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog;

use CommonPHP\Drivers\Logging\Monolog\Adapters\CommonPHPLogTargetHandler;
use CommonPHP\Drivers\Logging\Monolog\Exceptions\MonologConfigurationException;
use CommonPHP\Logging\Contracts\LogTargetInterface;
use CommonPHP\Logging\Enums\LogLevelValue;
use DateTimeZone;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NoopHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;

final class MonologHandlerFactory
{
    public function __construct(
        private readonly MonologFormatterFactory $formatters = new MonologFormatterFactory(),
    ) {
    }

    public function stream(
        mixed $stream,
        mixed $level = LogLevelValue::Debug,
        bool $bubble = true,
        ?FormatterInterface $formatter = null,
        ?int $filePermission = null,
        bool $useLocking = false,
        string $fileOpenMode = 'a',
    ): StreamHandler {
        if (!is_string($stream) && !is_resource($stream)) {
            throw new MonologConfigurationException('Stream handler requires a stream resource or string path.');
        }

        $handler = new StreamHandler(
            $stream,
            MonologLogDriver::toMonologLevel($level),
            $bubble,
            $filePermission,
            $useLocking,
            $fileOpenMode,
        );

        return $this->withFormatter($handler, $formatter);
    }

    public function file(
        string $path,
        mixed $level = LogLevelValue::Debug,
        bool $bubble = true,
        ?FormatterInterface $formatter = null,
        ?int $filePermission = null,
        bool $useLocking = false,
        string $fileOpenMode = 'a',
    ): StreamHandler {
        return $this->stream($path, $level, $bubble, $formatter, $filePermission, $useLocking, $fileOpenMode);
    }

    public function stdout(
        mixed $level = LogLevelValue::Debug,
        bool $bubble = true,
        ?FormatterInterface $formatter = null,
    ): StreamHandler {
        return $this->stream('php://stdout', $level, $bubble, $formatter);
    }

    public function stderr(
        mixed $level = LogLevelValue::Debug,
        bool $bubble = true,
        ?FormatterInterface $formatter = null,
    ): StreamHandler {
        return $this->stream('php://stderr', $level, $bubble, $formatter);
    }

    public function rotatingFile(
        string $path,
        int $maxFiles = 0,
        mixed $level = LogLevelValue::Debug,
        bool $bubble = true,
        ?FormatterInterface $formatter = null,
        ?int $filePermission = null,
        bool $useLocking = false,
        string $dateFormat = RotatingFileHandler::FILE_PER_DAY,
        string $filenameFormat = '{filename}-{date}',
        ?DateTimeZone $timezone = null,
    ): RotatingFileHandler {
        if ($maxFiles < 0) {
            throw new MonologConfigurationException('Rotating file handler maxFiles cannot be negative.');
        }

        $handler = new RotatingFileHandler(
            $path,
            $maxFiles,
            MonologLogDriver::toMonologLevel($level),
            $bubble,
            $filePermission,
            $useLocking,
            $dateFormat,
            $filenameFormat,
            $timezone,
        );

        return $this->withFormatter($handler, $formatter);
    }

    public function errorLog(
        mixed $level = LogLevelValue::Debug,
        bool $bubble = true,
        ?FormatterInterface $formatter = null,
        int $messageType = ErrorLogHandler::OPERATING_SYSTEM,
        bool $expandNewlines = false,
    ): ErrorLogHandler {
        $handler = new ErrorLogHandler(
            $messageType,
            MonologLogDriver::toMonologLevel($level),
            $bubble,
            $expandNewlines,
        );

        return $this->withFormatter($handler, $formatter);
    }

    public function null(mixed $level = LogLevelValue::Debug): NullHandler
    {
        return new NullHandler(MonologLogDriver::toMonologLevel($level));
    }

    public function noop(): NoopHandler
    {
        return new NoopHandler();
    }

    public function test(
        mixed $level = LogLevelValue::Debug,
        bool $bubble = true,
        ?FormatterInterface $formatter = null,
    ): TestHandler {
        $handler = new TestHandler(MonologLogDriver::toMonologLevel($level), $bubble);

        return $this->withFormatter($handler, $formatter);
    }

    public function target(LogTargetInterface $target, bool $bubble = true): CommonPHPLogTargetHandler
    {
        return new CommonPHPLogTargetHandler($target, $bubble);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fromArray(array $config): HandlerInterface
    {
        $type = strtolower(str_replace('-', '_', (string) ($config['type'] ?? 'stream')));
        $level = $config['level'] ?? LogLevelValue::Debug;
        $bubble = (bool) ($config['bubble'] ?? true);
        $formatter = $this->resolveFormatter($config['formatter'] ?? null);

        return match ($type) {
            'stream' => $this->stream(
                $config['stream'] ?? $config['path'] ?? null,
                $level,
                $bubble,
                $formatter,
                $config['file_permission'] ?? null,
                (bool) ($config['use_locking'] ?? false),
                (string) ($config['file_open_mode'] ?? 'a'),
            ),
            'file' => $this->file(
                $this->requireString($config, 'path', 'File handler requires a path.'),
                $level,
                $bubble,
                $formatter,
                $config['file_permission'] ?? null,
                (bool) ($config['use_locking'] ?? false),
                (string) ($config['file_open_mode'] ?? 'a'),
            ),
            'rotating_file', 'rotating' => $this->rotatingFile(
                $this->requireString($config, 'path', 'Rotating file handler requires a path.'),
                (int) ($config['max_files'] ?? 0),
                $level,
                $bubble,
                $formatter,
                $config['file_permission'] ?? null,
                (bool) ($config['use_locking'] ?? false),
                (string) ($config['date_format'] ?? RotatingFileHandler::FILE_PER_DAY),
                (string) ($config['filename_format'] ?? '{filename}-{date}'),
                $config['timezone'] instanceof DateTimeZone ? $config['timezone'] : null,
            ),
            'error_log', 'errorlog' => $this->errorLog(
                $level,
                $bubble,
                $formatter,
                (int) ($config['message_type'] ?? ErrorLogHandler::OPERATING_SYSTEM),
                (bool) ($config['expand_newlines'] ?? false),
            ),
            'null' => $this->null($level),
            'noop', 'no_op' => $this->noop(),
            'test' => $this->test($level, $bubble, $formatter),
            default => throw new MonologConfigurationException('Unsupported Monolog handler type "' . $type . '".'),
        };
    }

    public function withFormatter(HandlerInterface $handler, ?FormatterInterface $formatter): HandlerInterface
    {
        if ($formatter === null) {
            return $handler;
        }

        if (!$handler instanceof FormattableHandlerInterface) {
            throw new MonologConfigurationException(
                'Handler ' . $handler::class . ' does not support formatters.',
            );
        }

        $handler->setFormatter($formatter);

        return $handler;
    }

    private function resolveFormatter(mixed $formatter): ?FormatterInterface
    {
        if ($formatter === null) {
            return null;
        }

        if ($formatter instanceof FormatterInterface) {
            return $formatter;
        }

        if (is_array($formatter)) {
            return $this->formatters->fromArray($formatter);
        }

        if (is_string($formatter)) {
            return $this->formatters->fromArray(['type' => $formatter]);
        }

        throw new MonologConfigurationException('Formatter configuration must be a formatter, array, string, or null.');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requireString(array $config, string $key, string $message): string
    {
        $value = $config[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new MonologConfigurationException($message);
        }

        return $value;
    }
}
