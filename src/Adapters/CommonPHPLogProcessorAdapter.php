<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog\Adapters;

use CommonPHP\Drivers\Logging\Monolog\MonologRecordMapper;
use CommonPHP\Logging\Contracts\LogProcessorInterface;
use CommonPHP\Logging\Exceptions\LoggingException;
use CommonPHP\Logging\Exceptions\LogProcessorException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

final readonly class CommonPHPLogProcessorAdapter implements ProcessorInterface
{
    public function __construct(
        private LogProcessorInterface $processor,
    ) {
    }

    public function getProcessor(): LogProcessorInterface
    {
        return $this->processor;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        try {
            $processed = $this->processor->process(MonologRecordMapper::toCommonPHP($record));
        } catch (LoggingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new LogProcessorException(
                'CommonPHP log processor ' . $this->processor::class . ' failed: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }

        return MonologRecordMapper::toMonolog($processed);
    }
}
