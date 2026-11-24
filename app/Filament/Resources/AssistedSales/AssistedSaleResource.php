<?php

namespace App\Filament\Resources\AssistedSales;

use App\Filament\Resources\AssistedSales\Pages\ManageAssistedSales;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\AssistedSale;
use App\Models\ProductVariant;
use App\Services\AssistedSales;
use App\Services\Cart;
use App\Services\CommerceSettings;
use App\Services\Pricing;
use App\Support\CommerceAction;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssistedSaleResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Orders & customers';

    protected static ?string $model = AssistedSale::class;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-commerce') && \Illuminate\Support\Facades\Schema::hasTable('assisted_sales');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('manage-commerce') && ! $record->order_id;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::fields());
    }

    public static function fields(): array
    {
        return [
            Hidden::make('version'),
            Select::make('source')->options(['whatsapp' => 'WhatsApp', 'phone' => 'Phone'])->default('whatsapp')->required()->helperText('Where the customer contacted you. This does not send a message.'),
            TextInput::make('customer_label')->label('Customer name / reference (optional)')->placeholder('Example: Ali - black biker jacket')->helperText('A short reference to help you find this draft. The customer confirms delivery details at checkout.')->maxLength(255),
            TextInput::make('phone')->tel()->placeholder('Example: +923001234567')->helperText('Customer contact number, not your store number.')->maxLength(25),
            TextInput::make('email')->email()->placeholder('Example: customer@example.com')->helperText('Optional customer email. Do not enter a made-up address.')->maxLength(254),
            Textarea::make('internal_note')->placeholder('Example: Customer asked for a relaxed fit; confirm size before sharing.')->maxLength(2000)->helperText('Internal only. Never included in the customer preview or copied message.'),
            Repeater::make('lines')->label('Proposed basket')->schema([
                Select::make('variant_id')->label('Jacket, color and size')->placeholder('Search jacket name or SKU')->helperText('Select the exact color and size. Results include its PKR price and available stock.')->searchable()->required()->live()
                    ->getSearchResultsUsing(fn (string $search): array => ProductVariant::with('productColor.product.category')->where(fn ($query) => $query->where('sku', 'like', '%'.$search.'%')->orWhereHas('productColor.product', fn ($products) => $products->where('name', 'like', '%'.$search.'%')))->limit(40)->get()->mapWithKeys(fn ($variant): array => [$variant->id => AssistedSales::variantLabel($variant)])->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($variant = ProductVariant::with('productColor.product.category')->find($value)) ? AssistedSales::variantLabel($variant) : 'Unavailable SKU #'.$value),
                TextInput::make('quantity')->helperText('Number of pieces of this exact size and color.')->integer()->minValue(1)->maxValue(100)->default(1)->required()->live(onBlur: true),
            ])->columns(2)->minItems(1)->maxItems(50)->defaultItems(1)->reorderable(false)->live()->helperText('Repeated SKUs are combined into one quantity when saved. Stock is confirmed at checkout; saving or sharing does not reserve it.'),
            ViewEntry::make('preview')->view('filament.assisted-sale-preview')->viewData(function (Get $get): array {
                try {
                    $quote = app(Cart::class)->quote(AssistedSales::items($get('lines') ?? []), 'PKR');
                    $totals = null;
                    try {
                        $totals = CommerceSettings::totals($quote['subtotal']);
                    } catch (ValidationException) {
                    }

                    return ['lines' => AssistedSales::snapshot($quote), 'subtotal' => $quote['subtotal'], 'totals' => $totals, 'error' => null];
                } catch (ValidationException $exception) {
                    return ['lines' => [], 'subtotal' => 0, 'totals' => null, 'error' => 'Choose saved SKUs and whole quantities to preview your selection.'];
                }
            })->columnSpanFull(),
        ];
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()->label('Create assisted sale')->modalHeading('Create assisted sale')->modalDescription('Prepare a basket for a WhatsApp or phone customer. Save the draft, review and issue its link, then copy and send it yourself. The customer enters their address and confirms checkout.')->modalWidth('5xl')
            ->using(function (array $data): AssistedSale {
                $sale = null;
                CommerceAction::run(function () use ($data, &$sale): void {
                    $sale = app(AssistedSales::class)->save($data);
                });

                return $sale;
            })->successNotificationTitle('Draft saved. Stock is confirmed at checkout.');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reference')->searchable(),
            TextColumn::make('source')->formatStateUsing(fn (string $state): string => $state === 'whatsapp' ? 'WhatsApp' : 'Phone'),
            TextColumn::make('customer_label')->searchable(),
            TextColumn::make('subtotal_minor')->label('PKR merchandise subtotal')->formatStateUsing(fn ($state): string => Pricing::format((int) $state)),
            TextColumn::make('display_status')->label('Status')->badge(),
            TextColumn::make('updated_at')->label('Last update')->dateTime()->sortable(),
            TextColumn::make('expires_at')->label('Link expires')->dateTime()->placeholder('Not shared'),
        ])->defaultSort('updated_at', 'desc')->filters([
            SelectFilter::make('source')->options(['whatsapp' => 'WhatsApp', 'phone' => 'Phone']),
            SelectFilter::make('status')->options(['draft' => 'Draft', 'shared' => 'Shared', 'converted' => 'Converted', 'expired' => 'Expired', 'revoked' => 'Revoked'])->query(function ($query, array $data) {
                $status = $data['value'] ?? null;
                if ($status === 'expired') {
                    return $query->where('status', 'shared')->where('expires_at', '<=', now());
                }
                if ($status === 'shared') {
                    return $query->where('status', 'shared')->where('expires_at', '>', now());
                }

                return $status ? $query->where('status', $status) : $query;
            }),
        ])->recordActions([
            Action::make('editDraft')->label('Edit draft')->visible(fn (AssistedSale $record): bool => ! $record->order_id && $record->status !== 'shared')->schema(self::fields())->fillForm(fn (AssistedSale $record): array => AssistedSales::formData($record))->modalWidth('5xl')
                ->action(function (AssistedSale $record, array $data): void {
                    CommerceAction::run(fn () => app(AssistedSales::class)->save($data, $record));
                    Notification::make()->success()->title('Draft saved; repeated SKUs combined')->send();
                }),
            Action::make('share')->label(fn (AssistedSale $record): string => $record->status === 'shared' ? 'Edit and replace link' : 'Review and issue link')->visible(fn (AssistedSale $record): bool => ! $record->order_id)->schema(self::fields())->fillForm(fn (AssistedSale $record): array => AssistedSales::formData($record))->modalWidth('5xl')
                ->modalDescription('Review the customer-facing basket below. Issuing a link freezes this selection. Replacing immediately invalidates previous links. Nothing is sent automatically.')
                ->modalSubmitActionLabel('Issue replacement / new private link')
                ->action(function (AssistedSale $record, array $data): void {
                    CommerceAction::run(fn () => app(AssistedSales::class)->save($data, $record, share: true));
                    Notification::make()->success()->title('Private link ready')->body('Use Copy checkout link / message. Stock is confirmed at checkout.')->send();
                }),
            Action::make('copy')->label('Copy checkout link / message')->visible(fn (AssistedSale $record): bool => $record->display_status === 'shared')->modalSubmitAction(false)->modalHeading('Review and copy your offer')
                ->modalContent(function (AssistedSale $record) {
                    Gate::authorize('manage-commerce');
                    $link = $record->links()->whereNull('revoked_at')->latest('id')->firstOrFail();

                    return view('filament.assisted-sale-share', ['link' => $link, 'message' => AssistedSales::message($link)]);
                }),
            Action::make('revoke')->label('Revoke link')->visible(fn (AssistedSale $record): bool => $record->status === 'shared')->requiresConfirmation()
                ->action(function (AssistedSale $record): void {
                    CommerceAction::run(fn () => app(AssistedSales::class)->revoke($record));
                    Notification::make()->success()->title('Link revoked')->send();
                }),
            Action::make('duplicate')->label('Duplicate as new draft')->action(function (AssistedSale $record): void {
                CommerceAction::run(fn () => app(AssistedSales::class)->duplicate($record));
                Notification::make()->success()->title('New unshared draft created')->body('Open Edit draft to review current prices and unavailable items.')->send();
            }),
            Action::make('order')->label('Open resulting order')->visible(fn (AssistedSale $record): bool => $record->order_id !== null)->url(fn (AssistedSale $record): string => OrderResource::getUrl('view', ['record' => $record->order_id])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAssistedSales::route('/')];
    }
}
