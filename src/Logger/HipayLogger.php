<?php

namespace HiPay\Payment\Logger;

use HiPay\Payment\Service\ReadHipayConfigService;
use Psr\Log\LoggerInterface;

class HipayLogger implements LoggerInterface
{
    public const API = 'hipay_api';
    public const NOTIFICATION = 'hipay_notification';

    private LoggerInterface $logger;

    /** @var array<int|string, LoggerInterface> */
    private array $channels;

    private bool $debugMode;

    public function __construct(LoggerInterface $hipayApiLogger, LoggerInterface $hipayNotificationLogger, ReadHipayConfigService $config)
    {
        $this->channels = [
            static::API => $hipayApiLogger,
            static::NOTIFICATION => $hipayNotificationLogger,
        ];
        $this->logger = current($this->channels);

        $this->debugMode = $config->isDebugMode();
    }

    /**
     * Set a specific channel to log.
     */
    public function setChannel(string $channel): self
    {
        if (!isset($this->channels[$channel])) {
            $channels = implode('","', array_keys($this->channels));
            throw new \InvalidArgumentException('Channel '.$channel.' is invalid, "'.$channels.'" available.');
        }

        $this->logger = $this->channels[$channel];

        return $this;
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function emergency($message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function alert($message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function critical($message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function error($message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function warning($message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function notice($message, array $context = []): void
    {
        if ($this->debugMode) {
            $this->logger->notice($message, $context);
        }
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function info($message, array $context = []): void
    {
        if ($this->debugMode) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function debug($message, array $context = []): void
    {
        if ($this->debugMode) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
