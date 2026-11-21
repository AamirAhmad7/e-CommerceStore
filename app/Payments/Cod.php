<?php

namespace App\Payments;

final class Cod implements PaymentGateway
{
    public function code(): string
    {
        return 'cod';
    }

    public function enabled(array $settings): bool
    {
        return (bool) $settings['cod_enabled'];
    }

    public function instructions(array $settings): string
    {
        return 'Cash on delivery. Payment remains unpaid until receipt is recorded by the store.';
    }
}
