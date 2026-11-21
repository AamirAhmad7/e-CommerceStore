<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Services\PromotionManagement;
use App\Support\CommerceAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePromotions extends ManageRecords
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create promotion')->modalWidth('6xl')->using(fn (array $data) => CommerceAction::run(fn () => app(PromotionManagement::class)->save($data)))];
    }
}
