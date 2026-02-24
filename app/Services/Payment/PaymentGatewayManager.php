<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    public function registerGateway(string $name, PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$name] = $gateway;
    }

    public function getGateway(string $name): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$name])) {
            throw new InvalidArgumentException("Payment gateway '{$name}' is not registered.");
        }

        return $this->gateways[$name];
    }
}