<?php

namespace HiPay\Payment\Route\HipayCardToken;

use HiPay\Payment\Core\Checkout\Payment\HipayCardToken\HipayCardTokenCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class HipayCardTokenRouteResponse extends StoreApiResponse
{
    public function getCardTokens(): HipayCardTokenCollection
    {
        /** @var EntitySearchResult<HipayCardTokenCollection> $result */
        $result = $this->object;

        return $result->getEntities();
    }
}
