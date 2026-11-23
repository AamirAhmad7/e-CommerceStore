<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class SizeGuideFields
{
    public static function fields(): array
    {
        return [
            Select::make('unit')->options(['cm' => 'Centimetres (cm)', 'in' => 'Inches (in)'])->required(),
            Select::make('basis')->label('What is measured?')->options(['garment' => 'Garment measurements', 'body' => 'Body measurements', 'unknown' => 'Legacy chart - not yet clarified'])->required(),
            Textarea::make('note')->label('How to measure (your instructions)')->maxLength(2000)->rows(3),
            Repeater::make('measurements')->label('Measurement table')->minItems(1)->maxItems(12)->required()->defaultItems(0)->schema([
                TextInput::make('label')->label('Measurement, e.g. Chest')->required()->maxLength(80),
                Select::make('method')->label('How this value is measured')->options(['circumference' => 'Full circumference', 'flat_width' => 'Flat width (not circumference)', 'length' => 'Length', 'unknown' => 'Not yet clarified'])->required(),
                Textarea::make('instruction')->label('Where to measure')->helperText('Optional instructions for this row, for example shoulder seam to cuff. Do not change old values to guess a method.')->maxLength(500)->rows(2),
                Repeater::make('values')->label('Values by size')->minItems(1)->maxItems(40)->required()->defaultItems(0)->schema([
                    TextInput::make('size')->required()->maxLength(30)->distinct(),
                    TextInput::make('age_range')->label('Age range (optional, kids sizes)')->maxLength(40),
                    TextInput::make('value')->numeric()->required()->minValue(0.01)->maxValue(10000),
                ])->columns(2),
            ])->columnSpanFull(),
        ];
    }
}
