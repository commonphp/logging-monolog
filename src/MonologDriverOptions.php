<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog;

use CommonPHP\Drivers\Logging\Monolog\Exceptions\MonologConfigurationException;
use DateTimeZone;
use Monolog\Handler\HandlerInterface;
use Monolog\Processor\ProcessorInterface;

final readonly class MonologDriverOptions
{
    public string $channel;

    /**
     * @var list<HandlerInterface>
     */
    public array $handlers;

    /**
     * @var list<ProcessorInterface|callable>
     */
    public array $processors;

    /**
     * @param iterable<HandlerInterface> $handlers
     * @param iterable<ProcessorInterface|callable> $processors
     */
    public function __construct(
        string $channel = 'app',
        iterable $handlers = [],
        iterable $processors = [],
        public bool $includePsrLogMessageProcessor = true,
        public ?DateTimeZone $timezone = null,
        public bool $useMicrosecondTimestamps = true,
        public bool $detectCycles = true,
        public bool $useErrorLogFallback = true,
    ) {
        $channel = trim($channel);

        if ($channel === '') {
            throw new MonologConfigurationException('Monolog logger channel cannot be empty.');
        }

        $this->channel = $channel;
        $this->handlers = $this->normalizeHandlers($handlers);
        $this->processors = $this->normalizeProcessors($processors);
    }

    /**
     * @param iterable<HandlerInterface> $handlers
     * @return list<HandlerInterface>
     */
    private function normalizeHandlers(iterable $handlers): array
    {
        $normalized = [];

        foreach ($handlers as $handler) {
            if (!$handler instanceof HandlerInterface) {
                throw new MonologConfigurationException(
                    'Monolog handlers must implement ' . HandlerInterface::class . '.',
                );
            }

            $normalized[] = $handler;
        }

        return $normalized;
    }

    /**
     * @param iterable<ProcessorInterface|callable> $processors
     * @return list<ProcessorInterface|callable>
     */
    private function normalizeProcessors(iterable $processors): array
    {
        $normalized = [];

        foreach ($processors as $processor) {
            if (!$processor instanceof ProcessorInterface && !is_callable($processor)) {
                throw new MonologConfigurationException(
                    'Monolog processors must be callable or implement ' . ProcessorInterface::class . '.',
                );
            }

            $normalized[] = $processor;
        }

        return $normalized;
    }
}
