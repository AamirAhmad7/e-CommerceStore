<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Courier;
use App\Models\CourierSettlementLine;
use App\Services\CourierSettlements as Settlements;
use App\Services\Pricing;
use App\Support\CommerceAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

class CourierSettlements extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.courier-settlements';

    protected static string|\UnitEnum|null $navigationGroup = 'Orders';

    #[Url]
    public ?string $courier = null;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    #[Url]
    public ?string $orderFrom = null;

    #[Url]
    public ?string $orderUntil = null;

    #[Url]
    public bool $differences = false;

    public static function canAccess(): bool
    {
        return Gate::allows('verify-payments');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['courier', 'from', 'until', 'differences'], true)) {
            $this->resetPage($this->getTablePaginationPageName());
        }
    }

    public function table(Table $table): Table
    {
        return $table->query(fn () => CourierSettlementLine::query()->joinSub(Settlements::ledger(), 'ledger', 'ledger.line_id', '=', 'courier_settlement_lines.id')->select('courier_settlement_lines.*', 'ledger.difference', 'ledger.gross_difference')->with(['settlement.courier', 'shipment.order'])
            ->when($this->orderFrom, fn ($q) => $q->whereDate('ledger.order_date', '>=', $this->orderFrom))->when($this->orderUntil, fn ($q) => $q->whereDate('ledger.order_date', '<=', $this->orderUntil))->when($this->courier, fn ($q) => $q->where('ledger.courier_id', $this->courier))->when($this->from, fn ($q) => $q->whereDate('ledger.payout_date', '>=', $this->from))->when($this->until, fn ($q) => $q->whereDate('ledger.payout_date', '<=', $this->until))->when($this->differences, fn ($q) => $q->where(fn ($q) => $q->where('ledger.difference', '!=', 0)->orWhere('ledger.gross_difference', '!=', 0))))
            ->columns([
                TextColumn::make('settlement.external_reference')->label('Statement')->searchable(), TextColumn::make('settlement.courier.name')->label('Courier'), TextColumn::make('settlement.payout_date')->date(),
                TextColumn::make('tracking_number')->searchable(), TextColumn::make('shipment.order.number')->label('Order')->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record->shipment->order_id])),
                TextColumn::make('collection_minor')->label('Confirmed collected')->formatStateUsing(fn ($state) => Pricing::format((int) $state)),
                TextColumn::make('original_amounts')->label('Original gross / fee / net')->state(fn ($record): string => Pricing::format($record->gross_minor).' / '.Pricing::format($record->fee_minor).' / '.Pricing::format($record->net_minor)),
                TextColumn::make('difference')->label('Unresolved net (after corrections)')->formatStateUsing(fn ($state) => Pricing::format((int) $state)),
                TextColumn::make('gross_difference')->label('Gross vs confirmed difference')->formatStateUsing(fn ($state) => Pricing::format((int) $state)),
            ])->defaultSort('courier_settlement_lines.id', 'desc')->recordActions([
                Action::make('audit')->label('Statement and adjustments')->modalSubmitAction(false)->modalWidth('5xl')->modalContent(function (CourierSettlementLine $record) {
                    Gate::authorize('verify-payments');

                    return view('filament.settlement-audit', ['line' => $record]);
                }),
                Action::make('adjust')->label('Record adjustment')->modalDescription('Append a correction with an external reference. Enter signed PKR changes, not replacement totals. Original statement rows are retained; no bank transfer is initiated.')
                    ->schema([TextInput::make('reference')->required()->maxLength(150), TextInput::make('gross_delta')->label('Gross change (PKR)')->default('0.00')->required(), TextInput::make('fee_delta')->label('Fee change (PKR)')->default('0.00')->required(), TextInput::make('net_delta')->label('Net remittance change (PKR)')->default('0.00')->required(), Textarea::make('reason')->required()->maxLength(1000)])
                    ->action(function (CourierSettlementLine $record, array $data): void {
                        CommerceAction::run(fn () => app(Settlements::class)->adjust($record->id, $data));
                        Notification::make()->success()->title('Adjustment recorded; original statement retained')->send();
                    }),
            ])->paginated([10, 25, 50]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')->label('Download CSV template')->url(route('settlements.template')),
            Action::make('postStatement')->label('Enter or import statement')->modalWidth('7xl')->modalSubmitActionLabel('Post previewed statement')
                ->schema([
                    Select::make('courier_id')->label('Courier (including inactive historical couriers)')->options(fn () => Courier::pluck('name', 'id')->all())->required(),
                    TextInput::make('external_reference')->label('External statement reference')->required()->maxLength(150),
                    DatePicker::make('payout_date')->label('Actual payout date')->required()->maxDate(now()),
                    TextInput::make('bank_reference')->label('Bank / payout reference')->required()->maxLength(400),
                    FileUpload::make('csv_file')->label('Optional CSV upload; replaces manual rows below')->disk('local')->directory('courier-imports')->visibility('private')->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->maxSize(1024),
                    Repeater::make('rows')->label('Manual rows')->schema([TextInput::make('tracking_number')->label('Tracking (text)'), TextInput::make('gross_cod')->label('Gross COD (PKR)'), TextInput::make('fee_deduction')->label('Fee / deduction (PKR)'), TextInput::make('net_remitted')->label('Net remitted (PKR)')])->columns(4)->defaultItems(1)->maxItems(200),
                    Textarea::make('review_note')->label('Discrepancy review note (enter before preview)')->maxLength(1000),
                    Checkbox::make('accept_discrepancies')->label('I reviewed the differences; keep them unresolved until corrected'),
                    Hidden::make('token'),
                    Actions::make([Action::make('previewStatement')->label('Preview matching and amounts')->action(function (Get $get, Set $set): void {
                        CommerceAction::run(function () use ($get, $set): void {
                            $data = [];
                            foreach (['courier_id', 'external_reference', 'payout_date', 'bank_reference', 'rows', 'csv_file', 'review_note', 'accept_discrepancies'] as $field) {
                                $data[$field] = $get($field);
                            }
                            $plan = app(Settlements::class)->preview(self::input($data));
                            $set('token', $plan['token']);
                            $set('preview', collect($plan['rows'])->map(fn ($row): string => $row['tracking_number'].' / '.($row['order'] ?? 'No order').' / '.$row['result'].' / Confirmed '.Pricing::format($row['collection_minor']).' / Gross '.Pricing::format($row['gross_minor']).' / Fee '.Pricing::format($row['fee_minor']).' / Net '.Pricing::format($row['net_minor']).' / Net difference '.Pricing::format($row['difference']).' / Gross difference '.Pricing::format($row['gross_difference']))->implode("\n"));
                        });
                    })]),
                    Textarea::make('preview')->label('Matching preview')->readOnly()->dehydrated(false)->rows(10),
                ])->action(function (array $data): void {
                    CommerceAction::run(fn () => app(Settlements::class)->post(self::input($data), $data['token'] ?? ''));
                    Notification::make()->success()->title('Statement recorded once; review any unresolved differences below')->send();
                }),
        ];
    }

    private static function input(array $data): array
    {
        Gate::authorize('verify-payments');
        $file = $data['csv_file'] ?? null;
        if (is_array($file)) {
            $file = reset($file);
        }
        if ($file) {
            if ($file instanceof UploadedFile) {
                $contents = file_get_contents($file->getRealPath());
            } else {
                if (! is_string($file) || ! str_starts_with($file, 'courier-imports/') || str_contains($file, '..') || str_contains($file, '\\')) {
                    throw ValidationException::withMessages(['csv_file' => 'Choose a valid local CSV upload.']);
                }
                $contents = Storage::disk('local')->get($file);
            }
            $data['rows'] = app(Settlements::class)->csv($contents, (int) $data['courier_id'], (string) $data['external_reference']);
        }

        return $data;
    }
}
