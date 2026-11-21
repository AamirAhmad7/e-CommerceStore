<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\SupplierSources\SupplierSourceResource;
use App\Services\Pricing;
use App\Services\Replenishment as Service;
use App\Support\CommerceAction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class Replenishment extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.replenishment';

    protected static string|\UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Reorder needed';

    #[Url]
    public ?string $supplier = null;

    #[Url]
    public bool $showAll = false;

    #[Url]
    public ?string $product = null;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['supplier', 'showAll', 'product'], true)) {
            $this->resetPage($this->getTablePaginationPageName());
            $this->deselectAllTableRecords();
        }
    }

    public function table(Table $table): Table
    {
        return $table->emptyStateHeading('No configured SKUs need reorder')->emptyStateDescription('Set a reorder point and target on saved product sizes, then assign their preferred supplier.')->emptyStateActions([Action::make('configureReorder')->label('Open products to set reorder levels')->url(ProductResource::getUrl())])->query(fn () => Service::query(! $this->showAll)->when($this->product, fn ($q) => $q->whereHas('productColor', fn ($c) => $c->where('product_id', $this->product)))->when($this->supplier, fn ($q) => $q->where('supplier_id', $this->supplier)))->columns([
            TextColumn::make('sku')->searchable(), TextColumn::make('productColor.product.name')->label('Product'), TextColumn::make('productColor.name')->label('Color'), TextColumn::make('size'), TextColumn::make('available_now')->label('Available now'), TextColumn::make('incoming_units')->label('Incoming (issued only)'), TextColumn::make('reorder_point')->placeholder('Not configured'), TextColumn::make('reorder_target')->placeholder('Not configured'), TextColumn::make('supplier_name')->label('Preferred supplier')->placeholder('Assign a supplier'), TextColumn::make('suggestion')->state(fn ($record) => Service::suggestion($record))->label('Suggested units'), TextColumn::make('calculation')->state(fn ($record) => Service::explanation($record))->wrap(), TextColumn::make('existing_drafts')->label('Already in drafts')->placeholder('None')->wrap(),
        ])->recordActions([
            Action::make('settings')->label('Reorder settings')->fillForm(fn ($record) => ['point' => $record->reorder_point, 'target' => $record->reorder_target])->schema([TextInput::make('point')->label('Reorder point (blank = not configured)')->integer()->minValue(0)->maxValue(100000), TextInput::make('target')->label('Target quantity')->integer()->minValue(0)->maxValue(100000)])->action(function ($record, array $data): void {
                CommerceAction::run(fn () => app(Service::class)->settings([$record->id], filled($data['point']) ? (int) $data['point'] : null, filled($data['target']) ? (int) $data['target'] : null));
                Notification::make()->success()->title('Reorder settings saved')->send();
            }),
            Action::make('sources')->label('Assign supplier')->url(fn ($record) => SupplierSourceResource::getUrl('index', ['tableSearch' => $record->sku])),
        ])->toolbarActions([
            BulkAction::make('suggestDrafts')->label('Preview supplier drafts')->modalWidth('5xl')->fillForm(function ($records): array {
                $plan = CommerceAction::run(fn () => app(Service::class)->preview($records->modelKeys()));

                return ['ids' => $records->modelKeys(), 'key' => (string) Str::uuid(), 'token' => $plan['token'], 'preview' => collect($plan['rows'])->groupBy('supplier_name')->map(fn ($rows, $supplier) => ($supplier ?: 'MISSING PREFERRED SUPPLIER')."\n".$rows->map(fn ($r) => $r['sku'].' x '.$r['quantity'].' @ '.Pricing::format($r['cost']).' | Existing drafts: '.($r['drafts'] ?: 'none').' | '.$r['calculation'])->implode("\n"))->implode("\n\n")];
            })->schema([Hidden::make('ids'), Hidden::make('key'), Hidden::make('token'), Textarea::make('preview')->label('Supplier grouping, quantities and current quoted costs')->rows(12)->readOnly()->dehydrated(false)])->modalDescription('Review existing drafts before creating another. Missing costs are allowed in drafts but must be completed before issue. Nothing is sent or received.')->modalSubmitActionLabel('Create reviewed drafts')->action(function (array $data): void {
                $ids = CommerceAction::run(fn () => app(Service::class)->createDrafts($data['ids'], $data['key'], $data['token']));
                Notification::make()->success()->title('Created '.count($ids).' supplier drafts')->body('Open Purchasing overview > Drafts to review and issue.')->send();
            }),
        ])->selectCurrentPageOnly()->paginated([10, 25, 50]);
    }
}
