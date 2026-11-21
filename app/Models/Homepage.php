<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Homepage extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['draft' => 'array', 'published' => 'array', 'published_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('draft_hero')->useDisk(config('store.media_disk'))->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('published_heroes')->useDisk(config('store.media_disk'))->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('storefront')->performOnCollections('draft_hero', 'published_heroes')->fit(Fit::Max, 1600, 1600)->format('webp')->quality(80)->withResponsiveImages()->queued();
    }

    public function publishDraft(): void
    {
        DB::transaction(function (): void {
            $home = self::lockForUpdate()->findOrFail($this->id);
            $snapshot = $home->draft;
            if (! $snapshot || ! filled($snapshot['headline'] ?? null)) {
                throw ValidationException::withMessages(['headline' => 'Save a homepage draft first.']);
            }
            $hero = $home->getFirstMedia('draft_hero');
            $snapshot['hero_media_id'] = null;
            if ($hero) {
                $archived = $home->getMedia('published_heroes')->first(fn ($m) => (int) $m->getCustomProperty('source_media_id') === $hero->id);
                if (! $archived) {
                    $archived = $hero->copy($home, 'published_heroes', $hero->disk);
                    $archived->setCustomProperty('source_media_id', $hero->id)->save();
                }
                $snapshot['hero_media_id'] = $archived->id;
            }
            $home->update(['published' => $snapshot, 'published_at' => now()]);
        }, 3);
        $this->refresh();
    }
}
