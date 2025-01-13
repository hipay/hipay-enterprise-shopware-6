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
    private const NAME = 'hipay:notification';

    public function __construct(
        protected NotificationService $notificationService
    ) {
        parent::__construct(self::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Dispatch received HiPay notifications.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->notificationService->dispatchNotifications();
        } catch (\Throwable $e) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
