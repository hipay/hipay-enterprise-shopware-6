<?php

namespace HiPay\Payment\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\Annotation\NoStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class HipayStorefrontController extends StorefrontController
{
    private EntityRepository $tokenRepo;

    public function __construct(EntityRepository $hipayCardTokenRepository)
    {
        $this->tokenRepo = $hipayCardTokenRepository;
    }

    /**
     * @Route("/account/creditcard/{idToken}", name="frontend.account.creditcard.delete", options={"seo"="false"}, methods={"DELETE"}, defaults={"XmlHttpRequest"=true, "_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     *
     * @NoStore
     */
    public function deleteCreditcard(string $idToken, SalesChannelContext $context): JsonResponse
    {
        try {
            $result = $this->tokenRepo->searchIds(
                (new Criteria([$idToken]))->addFilter(
                    new EqualsFilter('customerId', $context->getCustomer()->getId())
                ),
                $context->getContext()
            );

            if (1 !== $result->getTotal()) {
                throw new NotFoundHttpException();
            }

            $this->tokenRepo->delete([['id' => $idToken]], $context->getContext());

            return new JsonResponse(['success' => true]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'message' => 'Card token not found'], 404);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => ''], 500);
        }
    }
}
