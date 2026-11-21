<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Pages\PurchaseReceipts;
use App\Models\PurchaseOrder;
use App\Services\Pricing;
use App\Services\PurchaseReceiving;
use App\Services\Purchasing;
use App\Support\CommerceAction;
use App\Support\PurchaseForms;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationLabel = 'Purchasing overview';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|\UnitEnum|null $navigationGroup = 'Purchasing';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(PurchaseForms::fields());
    }

    public static function table(Table $table): Table
    {
        return $table->emptyStateHeading('No purchases in this view')->emptyStateDescription('Create a purchase order above, or open Drafts. Issued orders appear under Awaiting delivery.')->query(function ($livewire) {
            $q = PurchaseOrder::with('supplier')->withSum('lines as units', 'quantity')->withSum('lines as outstanding_units', DB::raw('quantity - accepted - damaged_short - short_closed'))->withSum('lines as merchandise_minor', DB::raw('quantity * unit_cost_minor'))->withCount(['lines as missing_costs' => fn ($l) => $l->whereNull('unit_cost_minor')])->when($livewire->supplier, fn ($q) => $q->where('supplier_id', $livewire->supplier))->when($livewire->product, fn ($q) => $q->whereHas('lines.variant.productColor', fn ($c) => $c->where('product_id', $livewire->product)));
            if (in_array($livewire->scope, ['awaiting', 'overdue'], true)) {
                $q->whereIn('status', PurchaseOrder::OPEN);
                if ($livewire->scope === 'overdue') {
                    $q->whereDate('expected_at', '<', today());
                }
            } elseif ($livewire->scope !== 'all') {
                $q->where('status', $livewire->scope);
            }

            return $q;
        })->header(fn ($livewire) => view('filament.purchasing-overview', ['supplierId' => $livewire->supplier, 'productId' => $livewire->product]))->columns([
            TextColumn::make('reference')->searchable(), TextColumn::make('supplier_name')->label('Supplier')->searchable(), TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => str_replace('_', ' ', $state)), TextColumn::make('expected_at')->date()->sortable(), TextColumn::make('units')->label('Ordered units'), TextColumn::make('outstanding_units')->label('Still expected')->state(fn ($record) => in_array($record->status, PurchaseOrder::OPEN, true) ? $record->outstanding_units : 0), TextColumn::make('merchandise_minor')->label('Merchandise')->formatStateUsing(fn ($state, $record) => $record->missing_costs ? 'Incomplete costs' : Pricing::format((int) $state)), TextColumn::make('freight_minor')->label('Freight estimate')->formatStateUsing(fn ($state) => Pricing::format((int) $state))->placeholder('Not entered'),
        ])->defaultSort('id', 'desc')->recordActions([
            Action::make('editDraft')->label('Edit draft')->visible(fn ($record) => $record->status === 'draft')->schema(PurchaseForms::fields())->modalWidth('7xl')->fillForm(fn ($record) => Purchasing::data($record))->action(function ($record, array $data): void {
                CommerceAction::run(fn () => app(Purchasing::class)->save($data, $record));
                Notification::make()->success()->title('Draft saved; no incoming or stock posted')->send();
            }),
            Action::make('issue')->label('Review and issue')->visible(fn ($record) => $record->status === 'draft')->fillForm(fn ($record) => ['version' => $record->version])->schema([Hidden::make('version')])->modalContent(fn ($record) => view('filament.purchase-order-review', ['order' => $record->fresh(['lines.variant.productColor.product', 'supplier'])]))->modalSubmitActionLabel('Issue purchase order')->action(function ($record, array $data): void {
                CommerceAction::run(fn () => app(Purchasing::class)->issue($record->id, (int) $data['version']));
                Notification::make()->success()->title('Issued; quantities are incoming, not sellable')->send();
            }),
            Action::make('print')->label('Supplier copy')->url(fn ($record) => route('purchases.print', $record))->openUrlInNewTab(),
            PurchaseForms::receiptAction()->visible(fn ($record) => in_array($record->status, PurchaseOrder::OPEN, true)),
            Action::make('receipts')->label('Receipt history')->url(fn ($record) => PurchaseReceipts::getUrl(['purchase' => $record->id])),
            Action::make('shortClose')->label('Close remaining shortfall')->visible(fn ($record) => in_array($record->status, PurchaseOrder::OPEN, true))->modalDescription('This closes every still-expected unit without adding stock. Damage already marked replacement-expected will no longer be incoming. A linked receipt records the decision.')
                ->fillForm(fn ($record) => ['baseline' => PurchaseReceiving::baseline($record), 'key' => (string) Str::uuid()])->schema([Hidden::make('baseline'), Hidden::make('key'), Textarea::make('reason')->required()->maxLength(2000)])->action(function ($record, array $data): void {
                    CommerceAction::run(function () use ($record, $data): void {
                        $rows = $record->fresh(['lines'])->lines->mapWithKeys(fn ($l) => [$l->id => ['short_closed' => $l->outstanding]])->all();
                        $plan = app(PurchaseReceiving::class)->preview($record->id, $data['baseline'], $rows);
                        app(PurchaseReceiving::class)->post($record->id, $data['key'], $plan['token'], $rows, null, $data['reason']);
                    });
                    Notification::make()->success()->title('Remaining balances short-closed')->send();
                }),
            Action::make('cancel')->visible(fn ($record) => in_array($record->status, ['draft', 'ordered'], true) && ! $record->receipts()->exists())->fillForm(fn ($record) => ['version' => $record->version])->schema([Hidden::make('version'), Textarea::make('reason')->required()->maxLength(255)])->action(function ($record, array $data): void {
                CommerceAction::run(fn () => app(Purchasing::class)->cancel($record->id, (int) $data['version'], $data['reason']));
                Notification::make()->success()->title('Purchase order cancelled')->send();
            }),
            Action::make('duplicate')->label('Duplicate as draft')->action(function ($record): void {
                $new = CommerceAction::run(fn () => app(Purchasing::class)->duplicate($record));
                Notification::make()->success()->title('Created '.$new->reference)->body('Quotes refreshed. Review inactive SKUs and supplier availability before issue.')->send();
            }),
        ])->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePurchaseOrders::route('/')];
    }
}
