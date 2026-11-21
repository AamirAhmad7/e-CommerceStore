<?php

namespace App\Payments;

use Illuminate\Validation\ValidationException;

final class Gateways
{
    public static function all(): array
    {
        return [new Cod, new BankTransfer];
    }

    public static function get(string $code, array $settings): PaymentGateway
    {
        foreach (self::all() as $gateway) {
            if ($gateway->code() === $code && $gateway->enabled($settings)) {
                return $gateway;
            }
        }
        throw ValidationException::withMessages(['payment_method' => 'Choose a configured payment method.']);
    }
}
