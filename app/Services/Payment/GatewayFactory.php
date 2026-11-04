<?php

namespace App\Services\Payment;

use InvalidArgumentException;

class GatewayFactory
{
    /**
     * Resolve a gateway implementation by name.
     * Adding a new gateway = add a case here + implement the interface.
     * 
     * @param  string                  $gateway 
     * @return PaymentGatewayInterface
     */
    public static function make(string $gateway): PaymentGatewayInterface
    {
        return match($gateway) {
            'mercadopago' => new MercadoPagoGateway(),
            'pagseguro' => new PagSeguroGateway(),
            
            default => throw new InvalidArgumentException(
                "Unsupported payment gateway: [{$gateway}]. " . "Supported: mercadopago, pagseguro."
            ),
        };
    }

    /**
     * List all supported gateways.
     * 
     * @return array
     */
    public static function supported(): array
    {
        return ['mercadopago', 'pagseguro'];
    }

    /**
     * List supported payment methods per gateway.
     * 
     * @param  string $gateway
     * @return array
     */
    public static function methods(string $gateway): array 
    {
        return match($gateway){
            'mercadopago' => ['pix', 'boleto', 'card'],
            'pagseguro' => ['pix', 'boleto', 'card'],

            default => [],
        };
    }    
}
