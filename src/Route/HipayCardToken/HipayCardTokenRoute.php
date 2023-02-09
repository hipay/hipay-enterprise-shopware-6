<?php

namespace HiPay\Payment\Route\HipayCardToken;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class HipayCardTokenRoute extends AbstactHipayCardTokenRoute
{
    protected EntityRepository $tokenRepo;

    public function __construct(EntityRepository $tokenRepository)
    {
        $this->tokenRepo = $tokenRepository;
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Route("/store-api/customer/get-card-token", name="store-api.card-token.get", methods={"GET", "POST"})
     */
    public function load(Criteria $criteria, SalesChannelContext $context): ?HipayCardTokenRouteResponse
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customerId', $context->getCustomerId()));

        $cardTokens = $this->tokenRepo
            ->search($criteria, $context->getContext());

        return new HipayCardTokenRouteResponse($cardTokens);
    }
}
