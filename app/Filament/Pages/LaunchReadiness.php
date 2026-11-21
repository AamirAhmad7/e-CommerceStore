<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class LaunchReadiness extends Page
{
    protected string $view = 'filament.pages.launch-readiness';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    protected function getViewData(): array
    {
        Gate::authorize('manage-commerce');

        return ['findings' => \App\Support\LaunchReadiness::findings()];
    }
}
