<?php

namespace HiPay\Payment\Tests\Unit\Logger;

use HiPay\Payment\Logger\HipayLogger;
use HiPay\Payment\Service\ReadHipayConfigService;
use Monolog\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HipayLoggerTest extends TestCase
{
    public function provideLogger()
    {
        return [
            ['emergency'],
            ['alert'],
            ['critical'],
            ['error'],
            ['warning'],
            ['notice'],
            ['info'],
            ['debug'],
        ];
    }

    /**
     * @dataProvider provideLogger
     */
    public function testLoggerWithDebug($method)
    {
        /** @var LoggerInterface&MockObject */
        $handler = $this->createMock(LoggerInterface::class);
        $handler->expects($this->once())->method($method);
        $handler->method('log')->willReturnCallback(function ($level, $message, $context) use ($method) {
            $this->assertSame($method, $level);
        });

        /** @var ReadHipayConfigService&MockObject */
        $config = $this->createMock(ReadHipayConfigService::class);
        $config->method('isDebugMode')->willReturn(true);

        $logger = new HipayLogger(
            $handler,
            new NullLogger(),
            $config
        );

        $logger->$method('');
        $logger->log($method, '');
    }

    public function testSetChannel()
    {
        /** @var LoggerInterface&MockObject */
        $handler = $this->createMock(LoggerInterface::class);
        $handler->expects($this->once())->method('error');

        /** @var ReadHipayConfigService&MockObject */
        $config = $this->createMock(ReadHipayConfigService::class);
        $config->method('isDebugMode')->willReturn(true);

        $logger = new HipayLogger(
            $handler,
            new NullLogger(),
            $config
        );

        $logger->setChannel(HipayLogger::API);
        $logger->error('');
    }

    public function testSetChannelInvalid()
    {
        /** @var LoggerInterface&MockObject */
        $handler = $this->createMock(LoggerInterface::class);

        /** @var ReadHipayConfigService&MockObject */
        $config = $this->createMock(ReadHipayConfigService::class);
        $config->method('isDebugMode')->willReturn(true);

        $logger = new HipayLogger(
            new NullLogger(),
            new NullLogger(),
            $config
        );

        $this->expectException(\InvalidArgumentException::class);
        $logger->setChannel('FOO');
    }
}
