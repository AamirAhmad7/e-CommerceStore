<?php

namespace App\Filament\Pages;

use App\Models\Collection;
use App\Models\Homepage as HomeRecord;
use App\Support\SeoFields;
use App\Support\StoreSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class Homepage extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Storefront';

    protected string $view = 'filament.pages.homepage';

    protected static ?string $navigationLabel = 'Homepage';

    public ?array $data = [];

    public HomeRecord $record;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function mount(): void
    {
        Gate::authorize('manage-commerce');
        $this->record = HomeRecord::firstOrCreate(['key' => 'main']);
        $this->form->fill($this->record->draft ?? ['headline' => StoreSettings::brandName(), 'primary_target' => 'shop', 'featured' => []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->model($this->record)->statePath('data')->components([
            SeoFields::section('headline', 'supporting_text'),
            TextEntry::make('public_store_name')->label('Public store name')->state(fn (): string => StoreSettings::brandName()),
            Actions::make([Action::make('changeStoreName')->label('Change store name')->url(\App\Filament\Pages\StoreSettings::getUrl())->openUrlInNewTab()]),
            TextInput::make('headline')->label('Hero headline')->helperText('Campaign headline only. Saving a draft does not change the public store name or published homepage.')->required()->maxLength(160),
            Textarea::make('supporting_text')->label('Supporting text')->maxLength(1000)->rows(3),
            SpatieMediaLibraryFileUpload::make('hero')->label('Draft hero image')->collection('draft_hero')->disk(config('store.media_disk'))->visibility('public')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(8192),
            TextInput::make('hero_alt')->label('Hero image alt text')->maxLength(500),
            Select::make('primary_target')->label('Primary button destination')->options(['shop' => 'Shop', 'collection' => 'Published collection'])->required()->live(),
            Select::make('primary_collection_id')->label('Primary collection')->options(fn () => Collection::where('is_published', true)->pluck('title', 'id')->all())->visible(fn (Get $get) => $get('primary_target') === 'collection')->required(fn (Get $get) => $get('primary_target') === 'collection')->rules([Rule::exists('collections', 'id')->where('is_published', true)]),
            Repeater::make('featured')->label('Featured collections in display order')->defaultItems(0)->reorderableWithButtons()->addActionLabel('Feature a collection')->schema([
                Select::make('collection_id')->label('Collection')->options(fn () => Collection::where('is_published', true)->pluck('title', 'id')->all())->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems()->rules([Rule::exists('collections', 'id')->where('is_published', true)]),
            ]),
        ]);
    }

    public function saveDraft(): void
    {
        Gate::authorize('manage-commerce');
        $values = $this->form->getState();
        $this->record->update(['draft' => $values]);
        Notification::make()->title('Homepage draft saved')->success()->send();
    }

    public function publish(): void
    {
        Gate::authorize('manage-commerce');
        $this->saveDraft();
        $this->record->publishDraft();
        Notification::make()->title('Homepage published')->success()->send();
    }
}
