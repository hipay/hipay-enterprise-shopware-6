<?php

namespace HiPay\Payment\Route\HipayCardToken;

use HiPay\Payment\Core\Checkout\Payment\HipayCardToken\HipayCardTokenCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class HipayCardTokenRouteResponse extends StoreApiResponse
{
    public function getCardTokens(): HipayCardTokenCollection
    {
        return $this->object->getEntities();
    }
}
