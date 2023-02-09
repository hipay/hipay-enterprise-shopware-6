<?php

namespace HiPay\Payment\Command;

use HiPay\Payment\Service\NotificationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Hipay notification command.
 */
class NotificationCommand extends Command
{
    // Command name
    protected static $defaultName = 'hipay:notification';

    protected NotificationService $notifService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notifService = $notificationService;
    }

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Dispatch received HiPay notifications.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->notifService->dispatchNotifications();
        } catch (\Throwable $e) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
