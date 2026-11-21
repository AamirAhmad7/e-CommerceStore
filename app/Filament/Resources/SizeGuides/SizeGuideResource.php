<?php

namespace App\Filament\Resources\SizeGuides;

use App\Filament\Resources\Products\Schemas\SizeGuideFields;
use App\Models\SizeGuide;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class SizeGuideResource extends Resource
{
    protected static ?string $model = SizeGuide::class;

    protected static ?string $recordTitleAttribute = 'name';

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
        return $schema->components([TextInput::make('name')->required()->maxLength(160)->helperText('Reusable guide. Edits apply to every assigned product unless it uses a product-specific override.'), ...SizeGuideFields::fields()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('unit'), TextColumn::make('basis')])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSizeGuides::route('/'), 'create' => Pages\CreateSizeGuide::route('/create'), 'edit' => Pages\EditSizeGuide::route('/{record}/edit')];
    }
}
