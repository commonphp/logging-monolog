<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog\Adapters;

use CommonPHP\Drivers\Logging\Monolog\Exceptions\MonologHandlerException;
use CommonPHP\Drivers\Logging\Monolog\MonologRecordMapper;
use CommonPHP\Logging\Contracts\LogTargetInterface;
use CommonPHP\Logging\Exceptions\LoggingException;
use Monolog\Handler\Handler;
use Monolog\LogRecord;
use Throwable;

final class CommonPHPLogTargetHandler extends Handler
{
    public function __construct(
        private readonly LogTargetInterface $target,
        private bool $bubble = true,
    ) {
    }

    public function getTarget(): LogTargetInterface
    {
        return $this->target;
    }

    public function setBubble(bool $bubble): self
    {
        $this->bubble = $bubble;

        return $this;
    }

    public function getBubble(): bool
    {
        return $this->bubble;
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->target->handles(MonologRecordMapper::toCommonPHP($record));
    }

    public function handle(LogRecord $record): bool
    {
        $commonRecord = MonologRecordMapper::toCommonPHP($record);

        if (!$this->target->handles($commonRecord)) {
            return false;
        }

        try {
            $this->target->write($commonRecord);
        } catch (LoggingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MonologHandlerException(
                'CommonPHP log target "' . $this->target->getName() . '" failed: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }

        return !$this->bubble;
    }
}
