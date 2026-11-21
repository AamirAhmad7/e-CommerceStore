<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PromotionManagement
{
    public function prepare(array $data, ?Promotion $record = null): array
    {
        $data['code'] = Promotions::normalize($data['code'] ?? null) ?: null;
        Validator::make($data, ['name' => 'required|string|max:120', 'automatic' => 'required|boolean', 'code' => ['nullable', 'required_if:automatic,0', 'regex:/^[A-Z0-9][A-Z0-9_-]{1,39}$/', Rule::unique('promotions', 'code')->ignore($record?->id)], 'type' => 'required|in:percentage,fixed,shipping', 'percentage' => 'nullable|required_if:type,percentage|numeric|min:0.01|max:100', 'amount' => 'nullable|required_if:type,fixed|numeric|min:0.01', 'scope' => 'required|in:storewide,collections,products,variants', 'target_ids' => 'nullable|array|max:200', 'target_ids.*' => 'integer|distinct', 'minimum_units' => 'nullable|integer|min:1|max:5000', 'status' => 'required|in:draft,active,paused,archived', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'cap' => ['nullable', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'], 'minimum' => ['nullable', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'], 'preview_items' => 'nullable|array|max:10', 'preview_items.*.variant_id' => 'required|integer|exists:product_variants,id', 'preview_items.*.quantity' => 'required|integer|min:1|max:100', 'use_limit' => 'nullable|integer|min:1|max:10000000', 'landing_collection_id' => 'nullable|exists:collections,id'])->validate();
        if (filled($data['starts_at'] ?? null) && filled($data['ends_at'] ?? null) && Carbon::parse($data['ends_at'])->lte(Carbon::parse($data['starts_at']))) {
            throw ValidationException::withMessages(['ends_at' => 'End must follow the start.']);
        }
        if ($data['scope'] !== 'storewide') {
            $ids = array_values($data['target_ids'] ?? []);
            $class = match ($data['scope']) {
                'collections' => Collection::class,'products' => Product::class,'variants' => ProductVariant::class
            };
            if (! $ids || $class::whereIn('id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['target_ids' => 'Choose existing eligible items.']);
            }
        }
        $values = collect($data)->only(['name', 'automatic', 'type', 'scope', 'status', 'starts_at', 'ends_at', 'use_limit', 'minimum_units', 'landing_collection_id'])->all();
        foreach (['starts_at', 'ends_at', 'use_limit', 'minimum_units', 'landing_collection_id'] as $field) {
            $values[$field] = filled($values[$field] ?? null) ? $values[$field] : null;
        }

        return $values + ['example_items' => array_values($data['preview_items'] ?? []), 'code' => $data['automatic'] ? null : $data['code'], 'target_ids' => $data['scope'] === 'storewide' ? [] : array_map('intval', $data['target_ids']), 'percent_basis_points' => $data['type'] === 'percentage' ? Pricing::parse((string) $data['percentage'], 'PKR') : null, 'amount_minor' => $data['type'] === 'fixed' ? Pricing::parse((string) $data['amount'], 'PKR') : null, 'cap_minor' => $data['type'] === 'percentage' ? Pricing::parse($data['cap'] ?? null, 'PKR') : null, 'minimum_minor' => Pricing::parse($data['minimum'] ?? null, 'PKR')];
    }

    public function save(array $data, ?Promotion $record = null): Promotion
    {
        Gate::authorize('manage-commerce');
        $values = $this->prepare($data, $record);

        return DB::transaction(function () use ($values, $data, $record): Promotion {
            if ($record) {
                $record = Promotion::lockForUpdate()->findOrFail($record->id);
                if ($record->version !== (int) ($data['version'] ?? 0)) {
                    throw ValidationException::withMessages(['name' => 'This campaign changed. Reopen it before saving.']);
                }
            }
            if ($values['status'] === 'active' && Promotion::where('status', 'active')->where('id', '!=', $record?->id ?? 0)->count() >= 100) {
                throw ValidationException::withMessages(['status' => 'Archive or pause an offer first (100 active/scheduled offers maximum).']);
            }
            if (($values['use_limit'] ?? null) !== null && $values['use_limit'] < ($record?->uses ?? 0)) {
                throw ValidationException::withMessages(['use_limit' => 'Limit cannot be below confirmed uses.']);
            }
            $record ??= new Promotion;
            $record->fill($values);
            $record->version = ($record->version ?? 0) + 1;
            $record->save();

            return $record;
        }, 3);
    }

    public static function data(Promotion $p): array
    {
        return $p->toArray() + ['preview_items' => $p->example_items ?? [], 'percentage' => Pricing::decimal($p->percent_basis_points), 'amount' => Pricing::decimal($p->amount_minor), 'cap' => Pricing::decimal($p->cap_minor), 'minimum' => Pricing::decimal($p->minimum_minor)];
    }

    public function duplicate(Promotion $p): Promotion
    {
        Gate::authorize('manage-commerce');
        $data = self::data($p);
        $data['name'] = mb_substr($p->name.' (copy)', 0, 120);
        $data['status'] = 'draft';
        $data['starts_at'] = null;
        $data['ends_at'] = null;
        $data['code'] = $p->automatic ? null : 'NEXT-'.strtoupper(Str::random(8));

        return $this->save($data);
    }

    public function preview(array $data, array $items, ?Promotion $record = null): array
    {
        Gate::authorize('manage-commerce');
        $p = new Promotion($this->prepare($data, $record));
        $p->id = $record?->id;
        $quote = app(Cart::class)->quote($items);
        $settings = CommerceSettings::get();
        $known = $settings['shipping_minor'] !== null && $settings['tax_basis_points'] !== null;
        $settings['shipping_minor'] ??= 0;
        $settings['tax_basis_points'] ??= 0;

        return ['quote' => $quote, 'shipping_known' => $known, 'result' => app(Promotions::class)->resolve($quote,$settings,null,false,$p)];
    }
}
