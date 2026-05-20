<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Logging\Monolog\Tests\Unit;

use CommonPHP\Drivers\Logging\Monolog\MonologDriverOptions;
use CommonPHP\Drivers\Logging\Monolog\MonologHandlerFactory;
use CommonPHP\Drivers\Logging\Monolog\MonologLogDriver;
use CommonPHP\Drivers\Logging\Monolog\MonologLoggerFactory;
use CommonPHP\Logging\Contracts\LogProcessorInterface;
use CommonPHP\Logging\Contracts\LogTargetInterface;
use CommonPHP\Logging\Enums\LogLevelValue;
use CommonPHP\Logging\LogManager;
use CommonPHP\Logging\LogRecord as CommonPHPLogRecord;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;

final class MonologLogDriverTest extends TestCase
{
    public function testDriverDelegatesPsrLogsToMonolog(): void
    {
        $handler = new TestHandler();
        $driver = new MonologLogDriver(options: new MonologDriverOptions(
            channel: 'billing',
            handlers: [$handler],
            useErrorLogFallback: false,
        ));

        $driver->info('Invoice {invoice} paid', ['invoice' => 'A-100']);

        self::assertTrue($handler->hasInfo('Invoice A-100 paid'));
        self::assertSame('billing', $handler->getRecords()[0]->channel);
    }

    public function testLogManagerCanAddCommonPHPTargets(): void
    {
        $target = new class implements LogTargetInterface {
            /** @var list<CommonPHPLogRecord> */
            public array $records = [];

            public function getName(): string
            {
                return 'array';
            }

            public function handles(CommonPHPLogRecord $record): bool
            {
                return $record->level->isAtLeast(LogLevelValue::Warning);
            }

            public function write(CommonPHPLogRecord $record): void
            {
                $this->records[] = $record;
            }
        };

        $manager = new LogManager(new MonologLogDriver(options: new MonologDriverOptions(
            useErrorLogFallback: false,
        )));
        $manager->addTarget($target);

        $manager->info('Ignored');
        $manager->warning('Disk {disk} almost full', ['disk' => 'C:']);

        self::assertCount(1, $target->records);
        self::assertSame('warning', $target->records[0]->level->value);
        self::assertSame('Disk C: almost full', $target->records[0]->message);
        self::assertSame(['disk' => 'C:'], $target->records[0]->context);
    }

    public function testCommonPHPProcessorsCanMutateRecordsBeforeMonologHandlersRun(): void
    {
        $handler = new TestHandler();
        $processor = new class implements LogProcessorInterface {
            public function process(CommonPHPLogRecord $record): CommonPHPLogRecord
            {
                return $record->withExtraValue('tenant', 'demo');
            }
        };

        $driver = new MonologLogDriver(options: new MonologDriverOptions(
            handlers: [$handler],
            useErrorLogFallback: false,
        ));
        $driver->pushProcessor($processor);

        $driver->error('Something happened');

        self::assertSame('demo', $handler->getRecords()[0]->extra['tenant'] ?? null);
    }

    public function testFactoriesCreateUsableDriversFromConfig(): void
    {
        $factory = new MonologLoggerFactory();
        $manager = $factory->fromArray([
            'channel' => 'worker',
            'handlers' => [
                ['type' => 'test'],
            ],
            'processors' => [
                ['type' => 'uid', 'length' => 12],
            ],
            'use_error_log_fallback' => false,
        ]);

        $driver = $manager->getDriver();

        self::assertInstanceOf(MonologLogDriver::class, $driver);
        self::assertSame('worker', $driver->getChannel());
        self::assertCount(1, $driver->getHandlers());
        self::assertCount(2, $driver->getProcessors());
    }

    public function testHandlerFactoryMapsCommonPHPLevelsToMonologLevels(): void
    {
        $handler = (new MonologHandlerFactory())->test(LogLevelValue::Error);

        self::assertFalse($handler->isHandling(new \Monolog\LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'warning',
        )));
        self::assertTrue($handler->isHandling(new \Monolog\LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'error',
        )));
    }

    public function testInvalidLevelsUsePsrInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MonologLogDriver(options: new MonologDriverOptions(useErrorLogFallback: false)))
            ->log(new \stdClass(), 'Invalid');
    }
}
