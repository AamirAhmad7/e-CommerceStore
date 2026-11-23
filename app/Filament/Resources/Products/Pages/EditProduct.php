<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Schemas\ManagePricesAction;
use App\Filament\Resources\Products\Schemas\ManageStockAction;
use App\Filament\Resources\Products\Schemas\ReorderSettingsAction;
use App\Services\ProductPhotoPlacement;
use App\Support\CommerceAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditProduct extends EditRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    public static bool $formActionsAreSticky = true;

    protected static string $resource = ProductResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        return ($this->getRecord()->is_active ? 'Active' : 'Draft').' product - Preview shows saved changes.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([ManagePricesAction::make(), ManageStockAction::make()])->label('Price & stock')->icon('heroicon-o-banknotes')->button()->color('gray'),
            ActionGroup::make([
                ReorderSettingsAction::make(),
                Action::make('moveSharedPhoto')->label('Move shared photo')
                    ->modalDescription('Choose an existing shared photo and a saved color. This saves current product changes first, then appends the photo to that color. Existing files and alt text are retained.')
                    ->modalSubmitActionLabel('Save product and move photo')
                    ->schema([
                        Select::make('photo')->label('Shared photo')->required()->options(fn () => $this->getRecord()->getMedia('shared')->mapWithKeys(fn ($photo) => [$photo->id => $photo->file_name.' — '.$photo->getCustomProperty('alt', '')])->all()),
                        Select::make('color')->label('Destination color')->required()->options(fn () => $this->getRecord()->colors()->pluck('name', 'id')->all()),
                    ])
                    ->action(function (array $data): void {
                        abort_unless(ProductResource::canEdit($this->getRecord()), 403);
                        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                        if ($this->getErrorBag()->isNotEmpty()) {
                            throw new Halt;
                        }
                        CommerceAction::run(fn () => app(ProductPhotoPlacement::class)->moveToColor($this->getRecord(), (int) $data['photo'], (int) $data['color']));
                        $this->getRecord()->refresh();
                        $this->fillForm();
                        Notification::make()->success()->title('Photo moved; product saved')->send();
                    }),
            ])->label('Product tools')->icon('heroicon-o-chevron-down')->button()->color('gray'),
            Action::make('preview')
                ->label('Preview')->color('gray')
                ->url(fn (): string => route('products.preview', $this->getRecord()->slug))
                ->openUrlInNewTab()
                ->visible(fn (): bool => ! $this->getRecord()->trashed() && ProductResource::canEdit($this->getRecord())),
            ActionGroup::make([DeleteAction::make(), ForceDeleteAction::make(), RestoreAction::make()])->label('More')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray'),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Product saved')
            ->body('Product details, staged prices, colors, sizes and gallery changes saved. Stock changes use the separate stock confirmation.')
            ->persistent();
    }
}
