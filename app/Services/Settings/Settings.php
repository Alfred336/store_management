<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JsonException;

/** Read and write application settings with cache invalidation after commits. */
class Settings
{
    private const CACHE_PREFIX = 'app-settings:';

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(self::CACHE_PREFIX.$key, now()->addHour(), function () use ($key, $default): mixed {
            $encoded = Setting::query()->whereKey($key)->value('value');

            if ($encoded === null) {
                return $default;
            }

            try {
                return json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $default;
            }
        });
    }

    /** @param array<string, mixed> $values */
    public function putMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => json_encode($value, JSON_THROW_ON_ERROR)],
                );
            }
        });

        DB::afterCommit(function () use ($values): void {
            foreach (array_keys($values) as $key) {
                Cache::forget(self::CACHE_PREFIX.$key);
            }
        });
    }

    public function put(string $key, mixed $value): void
    {
        $this->putMany([$key => $value]);
    }
}
