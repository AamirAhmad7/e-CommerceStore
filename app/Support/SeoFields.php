<?php

namespace App\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

final class SeoFields
{
    public static function section(string $title, string $description): Section
    {
        return Section::make('Search and sharing')->collapsed()->columnSpanFull()->schema([
            TextInput::make('seo_title')->label('Search title (optional)')->maxLength(160)->live(onBlur: true)->helperText('Blank uses the page name and store name. This does not change the visible heading.'),
            Textarea::make('seo_description')->label('Search description (optional)')->maxLength(320)->rows(2)->live(onBlur: true)->helperText('A concise, accurate summary. Search engines may choose different wording.'),
            TextEntry::make('seo_preview_title')->label('Title preview')->state(fn (Get $get) => $get('seo_title') ?: ($get($title) ?: 'Page name').' | '.StoreSettings::brandName()),
            TextEntry::make('seo_preview_description')->label('Description preview')->state(fn (Get $get) => StorefrontSeo::description($get('seo_description') ?: $get($description) ?: 'Explore our published catalog.')),
        ]);
    }
}
