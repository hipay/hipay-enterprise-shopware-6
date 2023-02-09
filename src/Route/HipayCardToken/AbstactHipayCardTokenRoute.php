<?php

namespace HiPay\Payment\Route\HipayCardToken;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstactHipayCardTokenRoute
{
    abstract public function getDecorated(): self;

    abstract public function load(Criteria $criteria, SalesChannelContext $context): ?HipayCardTokenRouteResponse;
}
