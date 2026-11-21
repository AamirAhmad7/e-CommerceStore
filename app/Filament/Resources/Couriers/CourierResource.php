<?php

namespace App\Filament\Resources\Couriers;

use App\Filament\Resources\Couriers\Pages\ManageCouriers;
use App\Models\Courier;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CourierResource extends Resource
{
    protected static ?string $model = Courier::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Orders';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows('manage-commerce');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Courier display name')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('service_name')->maxLength(255),
            TextInput::make('tracking_template')->label('HTTPS tracking URL template')->maxLength(1000)->helperText('Optional. Example: https://courier.example/track?number={tracking}. No API calls are made.')
                ->rules([fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! Courier::validTemplate($value)) {
                        $fail('Use HTTPS and one {tracking} placeholder in the path or query, without credentials or a fragment.');
                    }
                }]),
            Toggle::make('is_active')->label('Available for new parcels')->default(true)->helperText('Deactivation retains historical shipments and tracking links.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('service_name'), IconColumn::make('is_active')->boolean()])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCouriers::route('/')];
    }
}
