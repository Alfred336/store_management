<?php

namespace App\Actions\Setup;

use App\Actions\Authorization\EnsureSystemRoles;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Create the first administrator and company settings in one transaction. */
class CompleteInitialSetup
{
    /**
     * @param  array{business_name: string, business_email: string|null, phone: string|null, address: string|null, tin: string|null, vrn: string|null, admin_name: string, admin_email: string, password: string}  $data
     */
    public function __invoke(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            Setting::query()->whereKey('business.currency')->lockForUpdate()->firstOrFail();

            if (User::query()->exists()) {
                throw ValidationException::withMessages([
                    'setup' => 'Initial setup has already been completed.',
                ]);
            }

            app(EnsureSystemRoles::class)();

            $user = new User;
            $user->forceFill([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
            ]);
            $user->save();
            $user->assignRole('Owner/Admin');

            app(Settings::class)->putMany([
                'business.name' => $data['business_name'],
                'business.email' => $data['business_email'],
                'business.phone' => $data['phone'],
                'business.address' => $data['address'],
                'business.tin' => $data['tin'],
                'business.vrn' => $data['vrn'],
                'business.currency' => 'TZS',
            ]);

            return $user;
        });
    }
}
