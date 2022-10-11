<?php

namespace HiPay\Payment\Tests\Tools;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

trait RequestStackMockTrait
{
    /**
     * @return RequestStack&MockObject
     */
    protected function getRequestStack(Request $request = null)
    {
        if (!$this instanceof TestCase) {
            throw new \Exception('The class '.static::class.' must extends '.TestCase::class);
        }

        if (null === $request) {
            $request = new Request();
        }

        /** @var RequestStack&MockObject */
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        return $requestStack;
    }
}
