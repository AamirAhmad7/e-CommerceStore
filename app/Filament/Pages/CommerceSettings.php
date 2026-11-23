<?php

namespace App\Filament\Pages;

use App\Services\CommerceSettings as Settings;
use App\Services\Pricing;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class CommerceSettings extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'filament.pages.commerce-settings';

    public array $data = [];

    public string $deliveryMode = 'unconfigured';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function mount(): void
    {
        Gate::authorize('manage-commerce');
        $this->data = Settings::get();
        $this->deliveryMode = $this->data['shipping_minor'] === null ? 'unconfigured' : ((int) $this->data['shipping_minor'] === 0 ? 'free' : 'fixed');
        foreach (['shipping_minor', 'free_shipping_minor'] as $field) {
            $this->data[$field] = Pricing::decimal($this->data[$field]);
        }

    }

    public function addInternationalRate(): void
    {
        Gate::authorize('manage-commerce');
        if (count($this->data['international_shipping_rates']) < 50) {
            $this->data['international_shipping_rates'][] = ['destination' => '', 'amount' => '', 'basis' => 'order'];
        }
    }

    public function removeInternationalRate(int $index): void
    {
        Gate::authorize('manage-commerce');
        unset($this->data['international_shipping_rates'][$index]);
        $this->data['international_shipping_rates'] = array_values($this->data['international_shipping_rates']);
    }

    public function save(): void
    {
        Gate::authorize('manage-commerce');
        $this->validate(['data.delivery_information' => 'nullable|string|max:5000', 'data.returns_information' => 'nullable|string|max:5000', 'data.returns_policy_approved' => 'boolean', 'data.returns_enabled' => 'boolean', 'data.return_days' => 'required|integer|min:1|max:365', 'data.contact_email' => 'nullable|email|max:254']);
        $this->validate(['data.shipping_name' => 'required|string|max:100', 'data.shipping_minor' => ['nullable', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'], 'data.free_shipping_minor' => ['nullable', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'], 'data.cod_enabled' => 'boolean', 'data.bank_enabled' => 'boolean', 'data.bank_instructions' => 'nullable|string|max:5000', 'data.expiry_hours' => 'required|integer|min:1|max:720']);
        $this->validate([
            'data.international_shipping_mode' => 'required|in:quote,included,separate',
            'data.international_shipping_rates' => 'array|max:50',
            'data.international_shipping_rates.*.destination' => 'required|string|max:80|distinct:ignore_case',
            'data.international_shipping_rates.*.amount' => ['required', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/'],
            'data.international_shipping_rates.*.basis' => 'required|in:order,item',
        ]);
        $this->validate(['deliveryMode' => 'required|in:unconfigured,free,fixed']);
        if ($this->deliveryMode === 'fixed') {
            $this->validate(['data.shipping_minor' => ['required', 'numeric', 'gt:0']]);
        }
        $values = $this->data;
        if ($this->deliveryMode !== 'fixed') {
            $values['shipping_minor'] = $this->deliveryMode === 'free' ? '0' : null;
        }
        foreach (['shipping_minor', 'free_shipping_minor'] as $field) {
            $values[$field] = Pricing::parse($values[$field], 'PKR');
        }
        $values['tax_basis_points'] = 0;
        $values['bank_instructions'] = $values['bank_instructions'] ?? '';
        Settings::save($values);
        Notification::make()->title('Commerce settings saved')->success()->send();
    }
}
