<?php

namespace HiPay\Payment\Tests\Unit\PaymentMethod;

use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Mapper\TransactionMapper;
use HiPay\Payment\PaymentMethod\Mybank;
use HiPay\Payment\Tests\Tools\PaymentMethodMockTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\HttpFoundation\Request;

class MybankTest extends TestCase
{
    use PaymentMethodMockTrait;

    public function testhydrateFields()
    {
        $response = [];

        $orderRequest = $this->getHostedFiledsOrderRequest(Mybank::class, $response);

        $this->assertSame(
            Mybank::getProductCode(),
            $orderRequest->payment_product
        );
    }

    public function testhydratePage()
    {
        $hostedPaymentPageRequest = $this->getHostedPagePaymentRequest(Mybank::class);

        $this->assertSame(
            Mybank::getProductCode(),
            $hostedPaymentPageRequest->payment_product_list
        );
    }

    public function testStatic()
    {
        $this->assertEquals(
            90,
            Mybank::getPosition()
        );

        $this->assertEquals(
            'hipay-mybank',
            Mybank::getTechnicalName()
        );

        $this->assertEquals(
            [],
            Mybank::addDefaultCustomFields()
        );

        $this->assertEquals(
            ['haveHostedFields' => false,  'allowPartialCapture' => false, 'allowPartialRefund' => true],
            Mybank::getCOnfig()
        );

        $this->assertSame(
            [
                'en-GB' => 'Pay your order by bank transfert with MyBank.',
                'de-DE' => 'Bezahlen Sie Ihre Bestellung per Banküberweisung mit MyBank.',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mybank::getDescription('en-GB'),
                'de-DE' => Mybank::getDescription('de-DE'),
                'fo-FO' => Mybank::getDescription('fo-FO'),
            ]
        );

        $this->assertSame(
            [
                'en-GB' => 'MyBank',
                'de-DE' => 'MyBank',
                'fo-FO' => null,
            ],
            [
                'en-GB' => Mybank::getName('en-GB'),
                'de-DE' => Mybank::getName('de-DE'),
                'fo-FO' => Mybank::getName('fo-FO'),
            ]
        );

        $this->assertSame(
            'mybank.svg',
            Mybank::getImage()
        );

        $this->assertSame(
            ['IT'],
            Mybank::getCountries()
        );

        $this->assertSame(
            ['EUR'],
            Mybank::getCurrencies()
        );
    }

    #[DataProvider('provideFinalizeSuccessStates')]
    public function testFinalizeProcessesOrderWhenHiPayTransactionIsSuccessful(string $state): void
    {
        $transaction = $this->generateTransaction([
            'transaction' => ['id' => 'mybank-tx-success'],
            'order' => ['order_number' => 12345],
        ]);

        /** @var OrderTransactionStateHandler&MockObject $handler */
        $handler = $this->createMock(OrderTransactionStateHandler::class);
        $handler->expects($this->once())
            ->method('process')
            ->with($this->equalTo($transaction['entity']->getId()));

        $mybank = $this->createMybankForFinalize(
            $handler,
            $this->createOrderTransactionRepository($transaction['entity']),
            [
                'requestOrderTransactionInformation' => [
                    (new TransactionMapper(['state' => $state]))->getModelObjectMapped(),
                ],
            ]
        );

        $mybank->finalize(
            new Request(),
            $transaction['struct'],
            $this->createMock(Context::class)
        );
    }

    public static function provideFinalizeSuccessStates(): array
    {
        return [
            [TransactionState::COMPLETED],
            [TransactionState::PENDING],
        ];
    }

    #[DataProvider('provideFinalizeCancelledStates')]
    public function testFinalizeInterruptsWhenHiPayTransactionIsNotSuccessful(?string $state): void
    {
        $transaction = $this->generateTransaction([
            'transaction' => ['id' => 'mybank-tx-cancelled'],
            'order' => ['order_number' => 12345],
        ]);

        /** @var OrderTransactionStateHandler&MockObject $handler */
        $handler = $this->createMock(OrderTransactionStateHandler::class);
        $handler->expects($this->never())->method('process');

        $responses = $state === null
            ? ['requestOrderTransactionInformation' => []]
            : [
                'requestOrderTransactionInformation' => [
                    (new TransactionMapper(['state' => $state]))->getModelObjectMapped(),
                ],
            ];

        $mybank = $this->createMybankForFinalize(
            $handler,
            $this->createOrderTransactionRepository($transaction['entity']),
            $responses
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('MyBank payment was cancelled');

        $mybank->finalize(
            new Request(),
            $transaction['struct'],
            $this->createMock(Context::class)
        );
    }

    public static function provideFinalizeCancelledStates(): array
    {
        return [
            'declined' => [TransactionState::DECLINED],
            'error' => [TransactionState::ERROR],
            'empty response' => [null],
        ];
    }

    public function testFinalizeInterruptsWhenHiPayApiFails(): void
    {
        $transaction = $this->generateTransaction([
            'transaction' => ['id' => 'mybank-tx-api-error'],
            'order' => ['order_number' => 12345],
        ]);

        /** @var OrderTransactionStateHandler&MockObject $handler */
        $handler = $this->createMock(OrderTransactionStateHandler::class);
        $handler->expects($this->never())->method('process');

        $mybank = $this->createMybankForFinalize(
            $handler,
            $this->createOrderTransactionRepository($transaction['entity']),
            [
                'requestOrderTransactionInformation' => static function (): void {
                    throw new \RuntimeException('API down');
                },
            ]
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Unable to verify MyBank payment status');

        $mybank->finalize(
            new Request(),
            $transaction['struct'],
            $this->createMock(Context::class)
        );
    }

    public function testFinalizeThrowsWhenOrderTransactionIsNotFound(): void
    {
        $transaction = $this->generateTransaction([
            'transaction' => ['id' => 'mybank-tx-missing'],
            'order' => ['order_number' => 12345],
        ]);

        /** @var OrderTransactionStateHandler&MockObject $handler */
        $handler = $this->createMock(OrderTransactionStateHandler::class);
        $handler->expects($this->never())->method('process');

        $orderTransactionRepo = $this->createMock(EntityRepository::class);
        $orderTransactionRepo->method('search')->willReturn(
            new EntitySearchResult(
                'order_transaction',
                0,
                new OrderTransactionCollection(),
                null,
                new Criteria(),
                $this->createMock(Context::class)
            )
        );

        $mybank = $this->createMybankForFinalize($handler, $orderTransactionRepo, []);

        $this->expectException(PaymentException::class);

        $mybank->finalize(
            new Request(),
            $transaction['struct'],
            $this->createMock(Context::class)
        );
    }

    public function testFinalizeInterruptsWhenReturnQueryParameterIsPresent(): void
    {
        $transaction = $this->generateTransaction([
            'transaction' => ['id' => 'mybank-tx-return'],
            'order' => ['order_number' => 12345],
        ]);

        /** @var OrderTransactionStateHandler&MockObject $handler */
        $handler = $this->createMock(OrderTransactionStateHandler::class);
        $handler->expects($this->never())->method('process');

        $mybank = $this->createMybankForFinalize(
            $handler,
            $this->createOrderTransactionRepository($transaction['entity']),
            []
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment error');

        $mybank->finalize(
            new Request(['return' => 'error']),
            $transaction['struct'],
            $this->createMock(Context::class)
        );
    }

    /**
     * @param array<string, mixed> $clientResponses
     */
    private function createMybankForFinalize(
        OrderTransactionStateHandler $handler,
        EntityRepository $orderTransactionRepository,
        array $clientResponses
    ): Mybank {
        return new Mybank(
            $handler,
            $this->getReadHipayConfig(['operationMode' => 'hostedPage']),
            $this->getClientService($clientResponses),
            $this->getRequestStack(),
            $this->getLocaleProvider(),
            $this->generateOrderCustomerRepo(),
            $orderTransactionRepository,
            $this->createMock(LoggerInterface::class),
        );
    }

    private function createOrderTransactionRepository(OrderTransactionEntity $orderTransaction): EntityRepository
    {
        $orderTransactionRepo = $this->createMock(EntityRepository::class);
        $orderTransactionRepo->method('search')->willReturn(
            new EntitySearchResult(
                'order_transaction',
                1,
                new OrderTransactionCollection([$orderTransaction]),
                null,
                new Criteria(),
                $this->createMock(Context::class)
            )
        );

        return $orderTransactionRepo;
    }
}