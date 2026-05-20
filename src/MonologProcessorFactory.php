<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog;

use CommonPHP\Drivers\Logging\Monolog\Adapters\CommonPHPLogProcessorAdapter;
use CommonPHP\Drivers\Logging\Monolog\Exceptions\MonologConfigurationException;
use CommonPHP\Logging\Contracts\LogProcessorInterface as CommonPHPProcessorInterface;
use CommonPHP\Logging\Enums\LogLevelValue;
use Monolog\Processor\HostnameProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\TagProcessor;
use Monolog\Processor\UidProcessor;

final class MonologProcessorFactory
{
    public function psrLogMessage(?string $dateFormat = null, bool $removeUsedContextFields = false): PsrLogMessageProcessor
    {
        return new PsrLogMessageProcessor($dateFormat, $removeUsedContextFields);
    }

    public function uid(int $length = 7): UidProcessor
    {
        return new UidProcessor($length);
    }

    public function memoryUsage(bool $realUsage = true, bool $useFormatting = true): MemoryUsageProcessor
    {
        return new MemoryUsageProcessor($realUsage, $useFormatting);
    }

    public function memoryPeakUsage(bool $realUsage = true, bool $useFormatting = true): MemoryPeakUsageProcessor
    {
        return new MemoryPeakUsageProcessor($realUsage, $useFormatting);
    }

    public function processId(): ProcessIdProcessor
    {
        return new ProcessIdProcessor();
    }

    public function hostname(): HostnameProcessor
    {
        return new HostnameProcessor();
    }

    /**
     * @param list<string> $skipClassesPartials
     */
    public function introspection(
        mixed $level = LogLevelValue::Debug,
        array $skipClassesPartials = [],
        int $skipStackFramesCount = 0,
    ): IntrospectionProcessor {
        return new IntrospectionProcessor(
            MonologLogDriver::toMonologLevel($level),
            $skipClassesPartials,
            $skipStackFramesCount,
        );
    }

    /**
     * @param list<string> $tags
     */
    public function tags(array $tags = []): TagProcessor
    {
        return new TagProcessor($tags);
    }

    public function commonPHP(CommonPHPProcessorInterface $processor): CommonPHPLogProcessorAdapter
    {
        return new CommonPHPLogProcessorAdapter($processor);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fromArray(array $config): ProcessorInterface
    {
        $type = strtolower(str_replace('-', '_', (string) ($config['type'] ?? 'psr_log_message')));

        return match ($type) {
            'psr_log_message', 'psr' => $this->psrLogMessage(
                $config['date_format'] ?? null,
                (bool) ($config['remove_used_context_fields'] ?? false),
            ),
            'uid' => $this->uid((int) ($config['length'] ?? 7)),
            'memory_usage' => $this->memoryUsage(
                (bool) ($config['real_usage'] ?? true),
                (bool) ($config['use_formatting'] ?? true),
            ),
            'memory_peak_usage', 'memory_peak' => $this->memoryPeakUsage(
                (bool) ($config['real_usage'] ?? true),
                (bool) ($config['use_formatting'] ?? true),
            ),
            'process_id', 'pid' => $this->processId(),
            'hostname', 'host' => $this->hostname(),
            'introspection' => $this->introspection(
                $config['level'] ?? LogLevelValue::Debug,
                $this->stringList($config['skip_classes_partials'] ?? []),
                (int) ($config['skip_stack_frames_count'] ?? 0),
            ),
            'tags', 'tag' => $this->tags($this->stringList($config['tags'] ?? [])),
            default => throw new MonologConfigurationException('Unsupported Monolog processor type "' . $type . '".'),
        };
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new MonologConfigurationException('Processor option must be a list of strings.');
        }

        $strings = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new MonologConfigurationException('Processor option must be a list of strings.');
            }

            $strings[] = $item;
        }

        return $strings;
    }
}
