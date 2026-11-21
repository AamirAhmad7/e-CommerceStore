<?php

namespace App\Filament\Pages;

use App\Support\StoreSettings as Settings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class StoreSettings extends Page
{
    protected string $view = 'filament.pages.store-settings';

    public string $brandName = '';

    public string $whatsappNumber = '';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function mount(): void
    {
        Gate::authorize('manage-commerce');
        $this->brandName = Settings::brandName();
        $this->whatsappNumber = Settings::whatsappNumber() ?? '';
    }

    public function save(): void
    {
        Gate::authorize('manage-commerce');
        $this->brandName = trim($this->brandName);
        $this->whatsappNumber = trim($this->whatsappNumber);

        $this->validate([
            'brandName' => ['required', 'string', 'max:120'],
            'whatsappNumber' => ['nullable', 'regex:'.Settings::WHATSAPP_NUMBER_PATTERN],
        ], [
            'brandName.required' => 'Enter a store name.',
            'brandName.max' => 'Use 120 characters or fewer.',
            'whatsappNumber.regex' => 'Use an international number with country code, 8–15 digits, and an optional leading +. Do not include spaces or punctuation.',
        ]);

        Settings::setBrandName($this->brandName);
        Settings::setWhatsappNumber($this->whatsappNumber);

        Notification::make()
            ->title('Store settings saved')
            ->success()
            ->send();
    }
}
