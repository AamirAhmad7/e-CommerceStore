<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Services\Replenishment;
use App\Support\CommerceAction;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

final class ReorderSettingsAction
{
    public static function make(): Action
    {
        return Action::make('reorderSettings')->label('Reorder settings')->visible(fn () => Gate::allows('manage-commerce') && Schema::hasColumn('product_variants', 'reorder_point'))->schema(fn (Product $record) => [
            CheckboxList::make('ids')->label('Saved sizes (point / target)')->options($record->fresh(['colors.variants'])->colors->flatMap(fn ($c) => $c->variants->map(fn ($v) => ['id' => $v->id, 'label' => $c->name.' / '.$v->size.' / '.$v->sku.' / '.($v->reorder_point ?? 'not configured').' / '.($v->reorder_target ?? 'not configured')]))->pluck('label', 'id')->all())->required(),
            TextInput::make('point')->label('Reorder point (blank clears setting)')->integer()->minValue(0)->maxValue(100000), TextInput::make('target')->integer()->minValue(0)->maxValue(100000), Toggle::make('keep')->label('Preserve already-configured exceptions')->default(true),
        ])->modalDescription('Select sizes to change. Leave exceptions unselected, or preserve all existing settings. Zero is configured; clear both fields to remove settings.')->action(function (Product $record, array $data): void {
            $count = CommerceAction::run(fn () => app(Replenishment::class)->settings(array_map('intval', $data['ids']), filled($data['point']) ? (int) $data['point'] : null, filled($data['target']) ? (int) $data['target'] : null, (bool) $data['keep'], $record->id));
            Notification::make()->success()->title('Updated reorder settings for '.$count.' sizes')->send();
        });
    }
}
