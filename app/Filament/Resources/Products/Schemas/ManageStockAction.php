<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Product;
use App\Services\Inventory;
use App\Services\Purchasing;
use App\Services\VariantPricing;
use App\Support\CommerceAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;

final class ManageStockAction
{
    public static function make(): Action
    {
        return Action::make('manageStock')->label('Manage stock')->visible(fn (): bool => Gate::allows('manage-commerce'))
            ->modalWidth('7xl')->modalHeading('Manage stock')
            ->modalDescription('Blank rows are skipped. Stock saves immediately; other product edits stay pending. For a stale balance, close and reopen to review it before re-entering your count.')
            ->modalSubmitActionLabel('Confirm stock preview')
            ->fillForm(function (Product $record, array $arguments): array {
                VariantPricing::authorize($record);
                $rows = [];
                foreach ($record->fresh(['colors.variants'])->colors->flatMap->variants as $variant) {
                    $rows[$variant->id] = ['snapshot' => app(Inventory::class)->adjustmentSnapshot($variant->id), 'quantity' => null];
                }

                return ['rows' => $rows, 'mode' => isset($arguments['variant'], $rows[$arguments['variant']]) && Inventory::readSnapshot($rows[$arguments['variant']]['snapshot'])['on_hand'] !== null ? 'receive' : 'opening', 'focus' => $arguments['variant'] ?? null];
            })
            ->schema(function (Product $record): array {
                $product = $record->fresh(['colors.variants']);

                return [
                    Hidden::make('focus'),
                    Actions::make([
                        Action::make('purchaseDelivery')->label('Open purchase orders for this product')->url(fn (): string => PurchaseOrderResource::getUrl('index', ['product' => $record->id, 'scope' => 'awaiting']))->openUrlInNewTab(),
                    ]),
                    Select::make('delivery_source')->label('Delivery source')->options(['independent' => 'Independent stock (no purchase order)', 'purchase' => 'Against a purchase order ? use Receive delivery there'])->default('independent')->required()->visible(fn (Get $get): bool => $get('mode') === 'receive'),
                    TextEntry::make('focused_sku')->label('Selected SKU')->state(fn (Get $get) => $product->colors->flatMap->variants->firstWhere('id', $get('focus'))?->sku ?? 'All saved sizes'),
                    Select::make('mode')->label('Operation')->options(['opening' => 'Set opening stock', 'receive' => 'Receive stock', 'count' => 'Correct physical count'])->required()->live(),
                    TextEntry::make('instructions')->state(fn (Get $get): string => $get('mode') === 'receive' ? 'Enter how many MORE arrived: 5 means add 5 units.' : 'Enter the TOTAL physical quantity: 5 means 5 in total. An explicit 0 sets the physical count to zero; blank skips the size.'),
                    ...$product->colors->flatMap->variants->flatMap(fn ($variant) => [
                        Hidden::make('rows.'.$variant->id.'.snapshot')->required(),
                        Hidden::make('rows.'.$variant->id.'.quantity')->nullable()->rules(['nullable', 'integer', 'min:0', 'max:1000000']),
                    ])->all(),
                    ViewEntry::make('stock_table')->view('filament.stock-entry-table')->viewData(fn (Get $get): array => ['product' => $product, 'focus' => $get('focus'), 'rows' => $get('rows')]),
                    TextInput::make('reason')->label('Reason')->required(fn (Get $get): bool => $get('mode') === 'count')->maxLength(500),
                    TextEntry::make('stock_preview')->label('Preview: SKU / current on hand / added or removed / resulting on hand')->state(function (Get $get): string {
                        $lines = [];
                        foreach ($get('rows') ?? [] as $row) {
                            if (($row['quantity'] ?? '') === '' || $row['quantity'] === null) {
                                continue;
                            }
                            $stock = Inventory::readSnapshot($row['snapshot']);
                            $next = $get('mode') === 'receive' ? (int) $stock['on_hand'] + (int) $row['quantity'] : (int) $row['quantity'];
                            $delta = $next - (int) $stock['on_hand'];
                            $lines[] = $stock['sku'].' / '.($stock['on_hand'] ?? 'Not configured').' / '.sprintf('%+d', $delta).' / '.$next;
                        }

                        return implode('; ', $lines) ?: 'Enter quantities to preview.';
                    }),
                ];
            })
            ->action(function (array $data, Product $record): void {
                $units = 0;
                CommerceAction::run(function () use (&$units, $record, $data): void {
                    if ($data['mode'] === 'receive' && ($data['delivery_source'] ?? null) !== 'independent') {
                        Purchasing::invalid('Open the purchase order above and use Receive delivery. That records stock and receipt history together.');
                    }
                    $units = app(Inventory::class)->adjustBatch($record, $data['rows'], $data['mode'], filled($data['reason'] ?? null) ? $data['reason'] : 'Delivery entry');
                });
                $count = count(array_filter($data['rows'], fn ($row) => ($row['quantity'] ?? '') !== '' && $row['quantity'] !== null));
                $record->refresh();
                Notification::make()->success()->title(($data['mode'] === 'receive' ? 'Received '.$units.' units' : 'Stock updated').' across '.$count.($count === 1 ? ' size.' : ' sizes.'))->send();
            });
    }
}
