<?php

namespace HiPay\Payment\Tests\Unit\Controller;

use HiPay\Payment\Controller\HipayStorefrontController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;

class HipayStorefrontControllerTest extends TestCase
{
    public function testDeleteCard()
    {
        /** @var IdSearchResult&MockObject */
        $result = $this->createMock(IdSearchResult::class);
        $result->method('getTotal')->willReturn(1);

        /** @var EntityRepository&MockObject */
        $tokenRepo = $this->createMock(EntityRepository::class);
        $tokenRepo->method('searchIds')->willReturn($result);
        $tokenRepo->expects($this->once())->method('delete');

        /** @var CustomerEntity&MockObject */
        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getId')->willReturn('CUSTOMER_ID');

        /** @var SalesChannelContext&MockObject */
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $controller = new HipayStorefrontController($tokenRepo);

        $response = $controller->deleteCreditcard('TOKEN_ID', $context);

        $this->assertEquals(
            new JsonResponse(['success' => true]),
            $response
        );
    }

    public function testDeleteCardNotFound()
    {
        /** @var IdSearchResult&MockObject */
        $result = $this->createMock(IdSearchResult::class);
        $result->method('getTotal')->willReturn(42);

        /** @var EntityRepository&MockObject */
        $tokenRepo = $this->createMock(EntityRepository::class);
        $tokenRepo->method('searchIds')->willReturn($result);
        $tokenRepo->expects($this->never())->method('delete');

        /** @var CustomerEntity&MockObject */
        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getId')->willReturn('CUSTOMER_ID');

        /** @var SalesChannelContext&MockObject */
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $controller = new HipayStorefrontController($tokenRepo);

        $response = $controller->deleteCreditcard('TOKEN_ID', $context);

        $this->assertEquals(
            new JsonResponse(['success' => false, 'message' => 'Card token not found'], 404),
            $response
        );
    }

    public function testDeleteCardServerError()
    {
        /** @var EntityRepository&MockObject */
        $tokenRepo = $this->createMock(EntityRepository::class);
        $tokenRepo->method('searchIds')->willThrowException(new \Exception());
        $tokenRepo->expects($this->never())->method('delete');

        /** @var CustomerEntity&MockObject */
        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getId')->willReturn('CUSTOMER_ID');

        /** @var SalesChannelContext&MockObject */
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $controller = new HipayStorefrontController($tokenRepo);

        $response = $controller->deleteCreditcard('TOKEN_ID', $context);

        $this->assertEquals(
            new JsonResponse(['success' => false, 'message' => ''], 500),
            $response
        );
    }
}
