<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = ProductResource::class;
}
