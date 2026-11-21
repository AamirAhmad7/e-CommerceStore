<?php

namespace App\Filament\Pages;

use App\Services\CommerceSettings as Settings;
use App\Services\Pricing;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class CommerceSettings extends Page
{
    protected string $view = 'filament.pages.commerce-settings';

    public array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function mount(): void
    {
        Gate::authorize('manage-commerce');
        $this->data = Settings::get();
        foreach (['shipping_minor', 'free_shipping_minor'] as $field) {
            $this->data[$field] = Pricing::decimal($this->data[$field]);
        }
        $this->data['tax_basis_points'] = Pricing::decimal($this->data['tax_basis_points']);
    }

    public function save(): void
    {
        Gate::authorize('manage-commerce');
        $this->validate(['data.delivery_information' => 'nullable|string|max:5000', 'data.returns_information' => 'nullable|string|max:5000', 'data.returns_policy_approved' => 'boolean', 'data.returns_enabled' => 'boolean', 'data.return_days' => 'required|integer|min:1|max:365', 'data.contact_email' => 'nullable|email|max:254']);
        $this->validate(['data.shipping_name' => 'required|string|max:100', 'data.shipping_minor' => ['nullable', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'], 'data.free_shipping_minor' => ['nullable', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'], 'data.tax_basis_points' => ['nullable', 'regex:/^(?:\d{1,2}(?:\.\d{1,2})?|100(?:\.00?)?)$/'], 'data.cod_enabled' => 'boolean', 'data.bank_enabled' => 'boolean', 'data.bank_instructions' => 'nullable|string|max:5000', 'data.expiry_hours' => 'required|integer|min:1|max:720']);
        $values = $this->data;
        foreach (['shipping_minor', 'free_shipping_minor', 'tax_basis_points'] as $field) {
            $values[$field] = Pricing::parse($values[$field], 'PKR');
        }
        $values['bank_instructions'] = $values['bank_instructions'] ?? '';
        Settings::save($values);
        Notification::make()->title('Commerce settings saved')->success()->send();
    }
}
