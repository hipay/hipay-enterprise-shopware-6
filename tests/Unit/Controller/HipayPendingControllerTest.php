<?php

namespace HiPay\Payment\Tests\Unit\Controller;

use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Model\Transaction;
use HiPay\Payment\Controller\HipayPendingController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HipayPendingControllerTest extends TestCase
{
    public function testResolveStatusWithoutTransaction()
    {
        $this->assertSame(HipayPendingController::STATUS_PENDING, HipayPendingController::resolveStatus(null));
    }

    /**
     * @dataProvider statesProvider
     */
    public function testResolveStatus(string $state, string $expected)
    {
        /** @var Transaction&MockObject */
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('getState')->willReturn($state);
        $transaction->method('getStatus')->willReturn(0);

        $this->assertSame($expected, HipayPendingController::resolveStatus($transaction));
    }

    /**
     * @dataProvider orderingProvider
     */
    public function testResolveStatusFromTransactionsIgnoresOrdering(array $states, string $expected)
    {
        $transactions = array_map(function (string $state) {
            /** @var Transaction&MockObject */
            $transaction = $this->createMock(Transaction::class);
            $transaction->method('getState')->willReturn($state);
            $transaction->method('getStatus')->willReturn(0);

            return $transaction;
        }, $states);

        $this->assertSame($expected, HipayPendingController::resolveStatusFromTransactions($transactions));
        $this->assertSame($expected, HipayPendingController::resolveStatusFromTransactions(array_reverse($transactions)));
    }

    public static function orderingProvider(): array
    {
        return [
            'none' => [[], HipayPendingController::STATUS_PENDING],
            'pending then completed' => [[TransactionState::PENDING, TransactionState::COMPLETED], HipayPendingController::STATUS_SUCCESS],
            'only pending' => [[TransactionState::PENDING], HipayPendingController::STATUS_PENDING],
            'pending then declined' => [[TransactionState::PENDING, TransactionState::DECLINED], HipayPendingController::STATUS_FAILED],
        ];
    }

    public static function statesProvider(): array
    {
        return [
            [TransactionState::COMPLETED, HipayPendingController::STATUS_SUCCESS],
            [TransactionState::PENDING, HipayPendingController::STATUS_PENDING],
            [TransactionState::FORWARDING, HipayPendingController::STATUS_PENDING],
            [TransactionState::DECLINED, HipayPendingController::STATUS_FAILED],
            [TransactionState::ERROR, HipayPendingController::STATUS_FAILED],
        ];
    }
}
