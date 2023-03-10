<?php

namespace HiPay\Payment\Formatter\Request;

use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;
use HiPay\Payment\Helper\Source;
use Ramsey\Uuid\Uuid;

/**
 * Class MaintenanceRequestFormatter.
 */
class MaintenanceRequestFormatter
{
    /**
     * Prepare Maintenance request.
     *
     * @param array<string, mixed> $params
     */
    public function makeRequest(array $params): MaintenanceRequest
    {
        $maintenanceRequest = new MaintenanceRequest();

        $maintenanceRequest->amount = $params['amount'] ?? null;
        $maintenanceRequest->operation = $params['operation'] ?? null;
        $maintenanceRequest->operation_id = Uuid::uuid4()->toString();
        $source = Source::toString();
        $maintenanceRequest->source = $source ?: null;

        if (isset($params['basket'])) {
            $basket = json_encode($params['basket']);
            $maintenanceRequest->basket = $basket ?: null;
        }

        return $maintenanceRequest;
    }
}
