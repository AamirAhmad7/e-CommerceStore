<?php

namespace App\Filament\Pages;

use App\Support\StoreSettings as Settings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\WithFileUploads;

class StoreSettings extends Page
{
    use WithFileUploads;

    public $printLogo = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'filament.pages.store-settings';

    public array $pages = [];

    public string $brandName = '';

    public string $whatsappNumber = '';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function mount(): void
    {
        Gate::authorize('manage-commerce');
        $this->pages = Settings::pages();
        $this->brandName = Settings::brandName();
        $this->whatsappNumber = Settings::whatsappNumber() ?? '';
    }

    public function save(): void
    {
        Gate::authorize('manage-commerce');
        $this->brandName = trim($this->brandName);
        $this->whatsappNumber = trim($this->whatsappNumber);

        $this->validate([
            'printLogo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048|dimensions:max_width=4000,max_height=4000',
            'pages.about' => 'nullable|string|max:6000',
            'pages.address' => 'nullable|string|max:500',
            'pages.hours' => 'nullable|string|max:500',
            'pages.privacy' => 'nullable|string|max:12000',
            'pages.terms' => 'nullable|string|max:12000',
            'brandName' => ['required', 'string', 'max:120'],
            'whatsappNumber' => ['nullable', 'regex:'.Settings::WHATSAPP_NUMBER_PATTERN],
        ], [
            'brandName.required' => 'Enter a store name.',
            'brandName.max' => 'Use 120 characters or fewer.',
            'whatsappNumber.regex' => 'Use an international number with country code, 8–15 digits, and an optional leading +. Do not include spaces or punctuation.',
        ]);

        if ($this->printLogo) {
            Settings::setPrintLogo($this->printLogo->store('branding', 'public'));
            $this->printLogo = null;
        }
        Settings::savePages($this->pages);
        Settings::setBrandName($this->brandName);
        Settings::setWhatsappNumber($this->whatsappNumber);

        Notification::make()
            ->title('Store settings saved')
            ->success()
            ->send();
    }
}
