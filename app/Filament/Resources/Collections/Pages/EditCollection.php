<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Filament\Resources\Collections\CollectionResource;
use App\Support\StorefrontCampaigns;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->url(fn () => route('collections.preview', $this->record->slug))->openUrlInNewTab()->label('Preview saved collection'),
            Action::make('copyPublicLink')->label('Copy public link')->visible(fn () => $this->record->is_published)
                ->modalHeading('Public collection link')->modalDescription('Copy this clean URL for WhatsApp or an ad. Drafts and tracking parameters are never included.')
                ->schema([TextInput::make('url')->label('Public link')->default(fn () => StorefrontCampaigns::url('collections.show', ['collection' => $this->record->slug]))->readOnly()->extraInputAttributes(['x-on:focus' => '$el.select()'])->suffixAction(Action::make('copy')->label('Copy link')->icon('heroicon-o-clipboard-document')->alpineClickHandler("Promise.resolve().then(() => navigator.clipboard.writeText(\$el.closest('[data-field-wrapper]').querySelector('input').value)).then(() => new FilamentNotification().title('Link copied').success().send()).catch(() => { const input = \$el.closest('[data-field-wrapper]').querySelector('input'); input.focus(); input.select(); new FilamentNotification().title('Select and copy the highlighted link').send(); })"))])
                ->modalSubmitAction(false)->modalCancelActionLabel('Close'),
            Action::make('publish')->label('Publish collection')->requiresConfirmation()->visible(fn () => ! $this->record->is_published)->action(function (): void {
                Gate::authorize('manage-commerce');
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                if ($this->getErrorBag()->isNotEmpty()) {
                    return;
                }
                $this->record->update(['is_published' => true]);
                $this->refreshFormData(['is_published']);
                Notification::make()->success()->title('Collection published')->send();
            }),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Collection saved';
    }
}
