<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

final class CommerceAction
{
    public static function run(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title('Action could not be completed')->body(collect($exception->errors())->flatten()->implode(' '))->send();
            throw new Halt;
        }
    }
}
