<?php

namespace App\Payments;

interface PaymentGateway
{
    public function code(): string;

    public function enabled(array $settings): bool;

    public function instructions(array $settings): string;
}
