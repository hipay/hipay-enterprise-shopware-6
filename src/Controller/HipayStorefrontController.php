<?php

namespace HiPay\Payment\Controller;

use HiPay\Payment\Core\Checkout\Payment\HipayCardToken\HipayCardTokenCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class HipayStorefrontController extends StorefrontController
{
    /**
     * @param EntityRepository<HipayCardTokenCollection> $hipayCardTokenRepository
     */
    public function __construct(
        private EntityRepository $hipayCardTokenRepository
    ) {}

    #[Route(
        path: "/account/creditcard/{idToken}",
        name: "frontend.account.creditcard.delete",
        options: ["seo" => "false"],
        methods: ["DELETE"],
        defaults: ["XmlHttpRequest" => true, "_loginRequired" => true, "_loginRequiredAllowGuest" => true]
    )]
    public function deleteCreditcard(string $idToken, SalesChannelContext $context): JsonResponse
    {
        try {
            $result = $this->hipayCardTokenRepository->searchIds(
                (new Criteria([$idToken]))->addFilter(
                    new EqualsFilter('customerId', $context->getCustomer()->getId())
                ),
                $context->getContext()
            );

            if ($result->getTotal() !== 1) {
                throw new NotFoundHttpException();
            }

            $this->hipayCardTokenRepository->delete([['id' => $idToken]], $context->getContext());

            return new JsonResponse(['success' => true]);
        } catch (NotFoundHttpException $e) {
            return new JsonResponse(['success' => false, 'message' => 'Card token not found'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => ''], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
