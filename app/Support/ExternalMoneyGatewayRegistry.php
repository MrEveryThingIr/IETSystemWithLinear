<?php

namespace App\Support;

final class ExternalMoneyGatewayRegistry
{
    /**
     * External fiat/payment integrations are intentionally capability
     * placeholders until a real provider is configured and audited.
     *
     * @return list<array{key:string,label:string,status:string,directions:list<string>}>
     */
    public function available(): array
    {
        return [
            [
                'key' => 'bank_transfer',
                'label' => 'Bank transfer',
                'status' => 'placeholder',
                'directions' => ['deposit', 'cashout'],
            ],
            [
                'key' => 'payment_provider',
                'label' => 'Payment provider',
                'status' => 'placeholder',
                'directions' => ['deposit', 'cashout'],
            ],
        ];
    }

    public function executable(string $key): bool
    {
        return false;
    }
}
