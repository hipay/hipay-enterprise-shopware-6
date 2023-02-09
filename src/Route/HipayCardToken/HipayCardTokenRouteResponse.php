<?php

namespace HiPay\Payment\Route\HipayCardToken;

use HiPay\Payment\Core\Checkout\Payment\HipayCardToken\HipayCardTokenCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class HipayCardTokenRouteResponse extends StoreApiResponse
{
    public function getCardTokens(): HipayCardTokenCollection
    {
        /** @var HipayCardTokenCollection $collection */
        /** @phpstan-ignore-next-line */
        $collection = $this->object->getEntities();

        return $collection;
    }
}
