<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog;

use CommonPHP\Logging\LogRecord as CommonPHPLogRecord;
use Monolog\LogRecord as MonologLogRecord;

final class MonologRecordMapper
{
    public static function toCommonPHP(MonologLogRecord $record): CommonPHPLogRecord
    {
        return new CommonPHPLogRecord(
            $record->level->toPsrLogLevel(),
            $record->message,
            $record->context,
            $record->datetime,
            $record->channel,
            $record->extra,
        );
    }

    public static function toMonolog(CommonPHPLogRecord $record): MonologLogRecord
    {
        return new MonologLogRecord(
            datetime: $record->timestamp,
            channel: $record->channel,
            level: MonologLogDriver::toMonologLevel($record->level),
            message: $record->message,
            context: $record->context,
            extra: $record->extra,
        );
    }
}
