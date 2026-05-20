<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog;

use CommonPHP\Drivers\Logging\Monolog\Adapters\CommonPHPLogFormatterAdapter;
use CommonPHP\Drivers\Logging\Monolog\Exceptions\MonologConfigurationException;
use CommonPHP\Logging\Contracts\LogFormatterInterface as CommonPHPFormatterInterface;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Formatter\ScalarFormatter;

final class MonologFormatterFactory
{
    public function line(
        ?string $format = null,
        ?string $dateFormat = null,
        bool $allowInlineLineBreaks = false,
        bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false,
    ): LineFormatter {
        return new LineFormatter(
            $format,
            $dateFormat,
            $allowInlineLineBreaks,
            $ignoreEmptyContextAndExtra,
            $includeStacktraces,
        );
    }

    public function json(
        int $batchMode = JsonFormatter::BATCH_MODE_JSON,
        bool $appendNewline = true,
        bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false,
    ): JsonFormatter {
        return new JsonFormatter($batchMode, $appendNewline, $ignoreEmptyContextAndExtra, $includeStacktraces);
    }

    public function normalizer(?string $dateFormat = null): NormalizerFormatter
    {
        return new NormalizerFormatter($dateFormat);
    }

    public function scalar(?string $dateFormat = null): ScalarFormatter
    {
        return new ScalarFormatter($dateFormat);
    }

    public function commonPHP(CommonPHPFormatterInterface $formatter): CommonPHPLogFormatterAdapter
    {
        return new CommonPHPLogFormatterAdapter($formatter);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fromArray(array $config): FormatterInterface
    {
        $type = strtolower(str_replace('-', '_', (string) ($config['type'] ?? 'line')));

        return match ($type) {
            'line' => $this->line(
                $config['format'] ?? null,
                $config['date_format'] ?? null,
                (bool) ($config['allow_inline_line_breaks'] ?? false),
                (bool) ($config['ignore_empty_context_and_extra'] ?? false),
                (bool) ($config['include_stacktraces'] ?? false),
            ),
            'json' => $this->json(
                (int) ($config['batch_mode'] ?? JsonFormatter::BATCH_MODE_JSON),
                (bool) ($config['append_newline'] ?? true),
                (bool) ($config['ignore_empty_context_and_extra'] ?? false),
                (bool) ($config['include_stacktraces'] ?? false),
            ),
            'normalizer', 'normalized' => $this->normalizer($config['date_format'] ?? null),
            'scalar' => $this->scalar($config['date_format'] ?? null),
            default => throw new MonologConfigurationException('Unsupported Monolog formatter type "' . $type . '".'),
        };
    }
}
