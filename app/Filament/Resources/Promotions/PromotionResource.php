<?php

namespace App\Filament\Resources\Promotions;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Services\Pricing;
use App\Services\PromotionManagement;
use App\Services\Promotions;
use App\Services\Purchasing;
use App\Support\CommerceAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function skuOptions(string $search): array
    {
        return ProductVariant::with('productColor.product')->where(fn ($q) => $q->where('sku', 'like', '%'.$search.'%')->orWhere('size', 'like', '%'.$search.'%')->orWhereHas('productColor', fn ($c) => $c->where('name', 'like', '%'.$search.'%')->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$search.'%'))))->limit(40)->get()->mapWithKeys(fn ($v) => [$v->id => Purchasing::variantLabel($v)])->all();
    }

    public static function skuLabel(mixed $id): ?string
    {
        $v = ProductVariant::with('productColor.product')->find($id);

        return $v ? Purchasing::variantLabel($v) : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('version'), TextInput::make('name')->label('Campaign name')->required()->maxLength(120), Toggle::make('automatic')->label('Automatic best offer (no code)')->default(false)->live(), TextInput::make('code')->label('Public code')->visible(fn (Get $get) => ! $get('automatic'))->required(fn (Get $get) => ! $get('automatic'))->maxLength(40)->regex('/^[A-Za-z0-9][A-Za-z0-9_-]{1,39}$/')->unique(ignoreRecord: true)->helperText('Case-insensitive. Letters, numbers, hyphen and underscore; at least 2 characters.'),
            Select::make('type')->options(['percentage' => 'Percentage off merchandise', 'fixed' => 'Fixed PKR off merchandise', 'shipping' => 'Free standard shipping'])->default('percentage')->required()->live(), TextInput::make('percentage')->label('Percentage')->default('10')->inputMode('decimal')->numeric()->minValue(0.01)->maxValue(100)->visible(fn (Get $get) => $get('type') === 'percentage')->required(fn (Get $get) => $get('type') === 'percentage'), TextInput::make('cap')->regex('/^\d{1,8}(?:\.\d{1,2})?$/')->label('Maximum discount PKR (optional)')->inputMode('decimal')->visible(fn (Get $get) => $get('type') === 'percentage'), TextInput::make('amount')->regex('/^\d{1,8}(?:\.\d{1,2})?$/')->label('Fixed discount PKR')->inputMode('decimal')->required(fn (Get $get) => $get('type') === 'fixed')->visible(fn (Get $get) => $get('type') === 'fixed'),
            Select::make('scope')->label('Eligible merchandise')->options(['storewide' => 'Storewide', 'collections' => 'Selected collections', 'products' => 'Selected products', 'variants' => 'Selected SKUs'])->default('storewide')->required()->live()->afterStateUpdated(fn (Set $set) => $set('target_ids', [])),
            Select::make('target_ids')->label('Eligible selection (duplicates count once)')->multiple()->searchable()->required(fn (Get $get) => $get('scope') !== 'storewide')->visible(fn (Get $get) => $get('scope') !== 'storewide')->getSearchResultsUsing(function (string $search, Get $get): array {
                return match ($get('scope')) {
                    'collections' => Collection::where('title', 'like', '%'.$search.'%')->limit(40)->pluck('title', 'id')->all(),'products' => Product::where('name', 'like', '%'.$search.'%')->limit(40)->pluck('name', 'id')->all(),default => self::skuOptions($search)
                };
            })->getOptionLabelsUsing(function (array $values, Get $get): array {
                return match ($get('scope')) {
                    'collections' => Collection::whereIn('id', $values)->pluck('title', 'id')->all(),'products' => Product::whereIn('id', $values)->pluck('name', 'id')->all(),default => ProductVariant::with('productColor.product')->whereIn('id', $values)->get()->mapWithKeys(fn ($v) => [$v->id => Purchasing::variantLabel($v)])->all()
                };
            }),
            TextInput::make('minimum')->regex('/^\d{1,8}(?:\.\d{1,2})?$/')->label('Minimum eligible merchandise PKR (optional)')->inputMode('decimal'), TextInput::make('minimum_units')->label('Minimum eligible units (optional)')->integer()->minValue(1)->maxValue(5000),
            Select::make('status')->options(['draft' => 'Draft', 'active' => 'Active / scheduled', 'paused' => 'Paused', 'archived' => 'Archived'])->default('draft')->required()->helperText('Active publishes immediately unless a future start is set. Dates use '.config('store.timezone').'.'), DateTimePicker::make('starts_at')->label('Starts at ('.config('store.timezone').')')->timezone(config('store.timezone'))->seconds(false), DateTimePicker::make('ends_at')->label('Ends at ('.config('store.timezone').')')->timezone(config('store.timezone'))->seconds(false), TextInput::make('use_limit')->label('Total confirmed-use limit (optional)')->integer()->minValue(1)->maxValue(10000000),
            Select::make('landing_collection_id')->label('Share link destination')->placeholder('Shop')->searchable()->options(fn () => Collection::where('is_published', true)->orderBy('title')->pluck('title', 'id')->all())->helperText('Only a currently active offer and published destination can be shared.'),
            Repeater::make('preview_items')->label('Example basket (saved for next time, never an order)')->defaultItems(0)->maxItems(10)->schema([Select::make('variant_id')->label('SKU')->searchable()->getSearchResultsUsing(fn (string $search) => self::skuOptions($search))->getOptionLabelUsing(fn ($value) => self::skuLabel($value))->required(), TextInput::make('quantity')->integer()->minValue(1)->maxValue(100)->default(1)->required()])->columns(2)->columnSpanFull(),
            Actions::make([Action::make('previewBasket')->label('Preview current rules and basket')->action(function (Get $get, Set $set, ?Promotion $record): void {
                CommerceAction::run(function () use ($get, $set, $record): void {
                    $fields = ['name', 'automatic', 'code', 'type', 'percentage', 'amount', 'cap', 'scope', 'target_ids', 'minimum', 'minimum_units', 'status', 'starts_at', 'ends_at', 'use_limit', 'landing_collection_id'];
                    $data = [];
                    foreach ($fields as $field) {
                        $data[$field] = $get($field);
                    } $items = [];
                    foreach ($get('preview_items') ?? [] as $row) {
                        if (! isset($row['variant_id']) || filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT) === false || $row['quantity'] < 1 || $row['quantity'] > 100) {
                            throw ValidationException::withMessages(['preview_items' => 'Choose a SKU and 1-100 units for each preview line.']);
                        }$items[(int) $row['variant_id']] = ($items[(int) $row['variant_id']] ?? 0) + (int) $row['quantity'];
                    }if (! $items) {
                        throw ValidationException::withMessages(['preview_items' => 'Add at least one SKU.']);
                    }$plan = app(PromotionManagement::class)->preview($data, $items, $record);
                    $r = $plan['result'];
                    $text = $r['snapshot']['summary']."\n";
                    foreach ($plan['quote']['lines'] as $line) {
                        $a = $r['lines'][$line['id']];
                        $text .= ($line['variant']?->sku ?? 'Missing SKU').' x '.$line['quantity'].': '.($a['eligible'] ? 'Eligible' : 'Excluded').' | discount '.Pricing::format($a['discount']).(! $line['valid'] ? ' | Currently unavailable at this quantity' : '')."\n";
                    }$text .= 'Eligible subtotal '.Pricing::format($r['snapshot']['eligible_minor']).'; merchandise saving '.Pricing::format($r['snapshot']['merchandise_discount_minor']).'; shipping saving '.Pricing::format($r['snapshot']['shipping_discount_minor']).'; payable '.Pricing::format($r['totals']['total_minor'])."\n".($r['error'] ?? 'Qualifies.').($plan['shipping_known'] ? '' : ' Shipping/tax not configured: displayed payable excludes unknown charges.');
                    $set('basket_preview', $text);
                });
            })]), Textarea::make('basket_preview')->label('Preview only: no stock, order or use changes')->readOnly()->dehydrated(false)->rows(9)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('code')->searchable()->placeholder('Automatic'), TextColumn::make('type')->badge(), TextColumn::make('status')->state(fn ($record) => $record->status === 'active' ? ($record->starts_at?->isFuture() ? 'scheduled' : ($record->availability() ?? 'active')) : $record->status), TextColumn::make('starts_at')->dateTime()->timezone(config('store.timezone')), TextColumn::make('ends_at')->dateTime()->timezone(config('store.timezone')), TextColumn::make('uses')->label('Confirmed uses'), TextColumn::make('use_limit')->placeholder('Unlimited')])->defaultSort('id', 'desc')->recordActions([
            EditAction::make()->label('Edit / preview')->modalWidth('6xl')->mutateRecordDataUsing(fn (array $data, Promotion $record) => PromotionManagement::data($record))->using(fn (array $data, Promotion $record) => CommerceAction::run(fn () => app(PromotionManagement::class)->save($data, $record))),
            Action::make('share')->label('Copy campaign link')->visible(fn ($record) => ! $record->availability())->modalSubmitAction(false)->modalContent(fn ($record) => view('filament.promotion-share', ['url' => $record->publicUrl(), 'summary' => Promotions::summary($record)])),
            Action::make('duplicate')->label('Duplicate as draft')->action(function (Promotion $record): void {
                $new = CommerceAction::run(fn () => app(PromotionManagement::class)->duplicate($record));
                Notification::make()->success()->title('Draft created: '.$new->name)->body('Dates cleared; a new code is ready to edit. Open Edit / preview.')->send();
            }),
            Action::make('results')->url(fn ($record) => self::getUrl('results', ['record' => $record])),
        ])->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePromotions::route('/'), 'results' => Pages\PromotionResults::route('/{record}/results')];
    }
}
