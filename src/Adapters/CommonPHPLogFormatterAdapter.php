<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog\Adapters;

use CommonPHP\Drivers\Logging\Monolog\MonologRecordMapper;
use CommonPHP\Logging\Contracts\LogFormatterInterface as CommonPHPFormatterInterface;
use CommonPHP\Logging\Exceptions\LogFormatterException;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use Throwable;

final readonly class CommonPHPLogFormatterAdapter implements FormatterInterface
{
    public function __construct(
        private CommonPHPFormatterInterface $formatter,
    ) {
    }

    public function getFormatter(): CommonPHPFormatterInterface
    {
        return $this->formatter;
    }

    public function format(LogRecord $record): string
    {
        try {
            return $this->formatter->format(MonologRecordMapper::toCommonPHP($record));
        } catch (LogFormatterException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new LogFormatterException(
                'CommonPHP log formatter ' . $this->formatter::class . ' failed: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * @param array<LogRecord> $records
     * @return list<string>
     */
    public function formatBatch(array $records): array
    {
        return array_map($this->format(...), $records);
    }
}
