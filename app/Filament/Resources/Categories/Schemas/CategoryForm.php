<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Support\SeoFields;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            SeoFields::section('name', 'description'),
            TextInput::make('name')
                ->label('Category name')
                ->required()
                ->maxLength(120),

            TextInput::make('slug')
                ->label('URL slug')
                ->helperText('Use lowercase letters, numbers, and hyphens. Example: leather-jackets')
                ->required()
                ->maxLength(160)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true),

            Textarea::make('description')
                ->rows(4)
                ->maxLength(5000)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }
}
