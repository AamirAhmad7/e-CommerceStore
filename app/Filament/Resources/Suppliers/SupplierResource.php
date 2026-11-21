<?php

namespace App\Filament\Resources\Suppliers;

use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $recordTitleAttribute = 'name';

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
        return $schema->components([TextInput::make('name')->required()->maxLength(255), TextInput::make('contact_person')->maxLength(255), TextInput::make('phone')->tel()->maxLength(40), TextInput::make('whatsapp')->label('WhatsApp digits with country code (optional)')->regex('/^[0-9]{7,15}$/')->maxLength(15), TextInput::make('city')->maxLength(255), TextInput::make('email')->email()->maxLength(254), Textarea::make('notes')->label('Internal notes')->maxLength(3000), Toggle::make('is_active')->label('Active supplier (turn off to archive)')->default(true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->emptyStateHeading('Add your first supplier')->emptyStateDescription('Create a supplier above, then map their products to your saved store SKUs.')->columns([TextColumn::make('name')->searchable()->url(fn ($record) => self::getUrl('view', ['record' => $record])), TextColumn::make('phone')->copyable(), TextColumn::make('city')->searchable(), IconColumn::make('is_active')->boolean()])->recordActions([Action::make('workspace')->url(fn ($record) => self::getUrl('view', ['record' => $record]))->label('Open workspace'), EditAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([ViewEntry::make('workspace')->view('filament.supplier-workspace')->viewData(fn (Supplier $record) => ['supplier' => $record])->columnSpanFull()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSuppliers::route('/'), 'view' => Pages\ViewSupplier::route('/{record}')];
    }
}
