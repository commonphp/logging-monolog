<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog;

use CommonPHP\Drivers\Logging\Monolog\Adapters\CommonPHPLogProcessorAdapter;
use CommonPHP\Drivers\Logging\Monolog\Adapters\CommonPHPLogTargetHandler;
use CommonPHP\Drivers\Logging\Monolog\Exceptions\MonologDriverException;
use CommonPHP\Logging\Contracts\AbstractLogDriver;
use CommonPHP\Logging\Contracts\LogProcessorInterface;
use CommonPHP\Logging\Contracts\LogTargetInterface;
use CommonPHP\Logging\Enums\LogLevelValue;
use CommonPHP\Logging\Exceptions\LoggingException;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\InvalidArgumentException as PsrInvalidArgumentException;
use Stringable;
use Throwable;

final class MonologLogDriver extends AbstractLogDriver
{
    private Logger $logger;

    public function __construct(
        ?Logger $logger = null,
        ?MonologDriverOptions $options = null,
    ) {
        $options ??= new MonologDriverOptions();
        $processors = $this->processorsFromOptions($options);
        $handlers = $this->handlersFromOptions($options, $logger);

        if ($logger === null) {
            $this->logger = new Logger(
                $options->channel,
                $handlers,
                $processors,
                $options->timezone,
            );
        } else {
            $this->logger = $logger;
            $this->pushHandlers($handlers);
            $this->pushMonologProcessors($processors);
        }

        $this->logger->useMicrosecondTimestamps($options->useMicrosecondTimestamps);
        $this->logger->useLoggingLoopDetection($options->detectCycles);
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getMonologLogger(): Logger
    {
        return $this->logger;
    }

    public function getChannel(): string
    {
        return $this->logger->getName();
    }

    public function isHandling(mixed $level): bool
    {
        return $this->logger->isHandling(self::toMonologLevel($level));
    }

    public function addTarget(LogTargetInterface $target, bool $bubble = true): self
    {
        return $this->pushHandler(new CommonPHPLogTargetHandler($target, $bubble));
    }

    public function pushHandler(HandlerInterface $handler): self
    {
        $this->logger->pushHandler($handler);

        return $this;
    }

    public function popHandler(): HandlerInterface
    {
        return $this->logger->popHandler();
    }

    /**
     * @param list<HandlerInterface> $handlers
     */
    public function setHandlers(array $handlers): self
    {
        $this->logger->setHandlers($handlers);

        return $this;
    }

    /**
     * @return list<HandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->logger->getHandlers();
    }

    public function pushProcessor(LogProcessorInterface|ProcessorInterface|callable $processor): self
    {
        if ($processor instanceof LogProcessorInterface) {
            $processor = new CommonPHPLogProcessorAdapter($processor);
        }

        $this->logger->pushProcessor($processor);

        return $this;
    }

    public function pushMonologProcessor(ProcessorInterface|callable $processor): self
    {
        $this->logger->pushProcessor($processor);

        return $this;
    }

    /**
     * @return ProcessorInterface|callable
     */
    public function popProcessor(): callable
    {
        return $this->logger->popProcessor();
    }

    /**
     * @return list<ProcessorInterface|callable>
     */
    public function getProcessors(): array
    {
        return $this->logger->getProcessors();
    }

    public function reset(): void
    {
        $this->logger->reset();
    }

    public function close(): void
    {
        $this->logger->close();
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $monologLevel = self::toMonologLevel($level);

        try {
            $this->logger->log($monologLevel, $message, $context);
        } catch (LoggingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MonologDriverException(
                'Monolog logger failed: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }

    public static function toMonologLevel(mixed $level): Level
    {
        if ($level instanceof LogLevelValue) {
            return Logger::toMonologLevel($level->value);
        }

        if ($level instanceof Stringable) {
            $level = (string) $level;
        }

        if (!$level instanceof Level && !is_string($level) && !is_int($level)) {
            throw new PsrInvalidArgumentException(
                'Unsupported log level type "' . get_debug_type($level) . '".',
            );
        }

        try {
            return Logger::toMonologLevel($level);
        } catch (Throwable $exception) {
            throw new PsrInvalidArgumentException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * @return list<HandlerInterface>
     */
    private function handlersFromOptions(MonologDriverOptions $options, ?Logger $logger): array
    {
        if ($options->handlers !== []) {
            return $options->handlers;
        }

        if (!$options->useErrorLogFallback) {
            return [];
        }

        if ($logger !== null && $logger->getHandlers() !== []) {
            return [];
        }

        return [new ErrorLogHandler()];
    }

    /**
     * @return list<ProcessorInterface|callable>
     */
    private function processorsFromOptions(MonologDriverOptions $options): array
    {
        $processors = $options->processors;

        if ($options->includePsrLogMessageProcessor) {
            $processors[] = new PsrLogMessageProcessor();
        }

        return $processors;
    }

    /**
     * @param list<HandlerInterface> $handlers
     */
    private function pushHandlers(array $handlers): void
    {
        foreach (array_reverse($handlers) as $handler) {
            $this->logger->pushHandler($handler);
        }
    }

    /**
     * @param list<ProcessorInterface|callable> $processors
     */
    private function pushMonologProcessors(array $processors): void
    {
        foreach (array_reverse($processors) as $processor) {
            $this->logger->pushProcessor($processor);
        }
    }
}
