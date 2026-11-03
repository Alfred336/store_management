<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $tin
 * @property string|null $vrn
 * @property string|null $notes
 * @property numeric-string $credit_limit
 * @property int $payment_terms_days
 * @property bool $is_active
 * @property bool $is_walk_in
 */
#[Fillable(['code', 'name', 'type', 'phone', 'email', 'address', 'tin', 'vrn', 'notes', 'credit_limit', 'payment_terms_days', 'is_active', 'is_walk_in'])]
class Customer extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
            'is_walk_in' => 'boolean',
        ];
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
