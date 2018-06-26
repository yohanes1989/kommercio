<?php

namespace Kommercio\ShippingMethods;

use Kommercio\Models\Order\DeliveryOrder\DeliveryOrder;
use Kommercio\Models\ShippingMethod\ShippingMethod;
use Kommercio\Models\Order\Order;

abstract class ShippingMethodAbstract
{
    protected $shippingMethod;

    public function getAvailableMethods()
    {
        return [];
    }

    public function setShippingMethod(ShippingMethod $shippingMethod)
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function validate($options = null)
    {
        return true;
    }

    public function getPrices($options = null)
    {
        return [];
    }

    public function requireAddress()
    {
        return TRUE;
    }

    public function useCustomPackagingSlip(DeliveryOrder $deliveryOrder)
    {
        return false;
    }

    public function customPackagingSlip(DeliveryOrder $deliveryOrder)
    {
        return false;
    }

    public function handleNewOrder(Order $order)
    {
        // Stub
    }
}
