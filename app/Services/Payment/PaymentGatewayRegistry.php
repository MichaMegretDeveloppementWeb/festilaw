<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Exceptions\Payment\PaymentException;

/**
 * Holds every ENABLED payment provider (config('payment.enabled')). Unlike the single-driver
 * signature Manager, several coexist here and the buyer picks one by key at checkout.
 */
final class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    /** @param  iterable<PaymentGatewayInterface>  $gateways */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->key()] = $gateway;
        }
    }

    /** @return array<string, PaymentGatewayInterface> */
    public function all(): array
    {
        return $this->gateways;
    }

    public function has(string $key): bool
    {
        return isset($this->gateways[$key]);
    }

    public function get(string $key): PaymentGatewayInterface
    {
        return $this->gateways[$key] ?? throw PaymentException::providerNotEnabled($key);
    }

    /**
     * key => label, for the checkout choice.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return array_map(fn (PaymentGatewayInterface $gateway): string => $gateway->label(), $this->gateways);
    }
}
