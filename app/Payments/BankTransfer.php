<?php

namespace App\Payments;

final class BankTransfer implements PaymentGateway
{
    public function code(): string
    {
        return 'bank';
    }

    public function enabled(array $settings): bool
    {
        return (bool) $settings['bank_enabled'] && trim($settings['bank_instructions']) !== '';
    }

    public function instructions(array $settings): string
    {
        return $settings['bank_instructions'];
    }
}
