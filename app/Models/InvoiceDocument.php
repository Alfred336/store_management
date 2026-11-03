<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded print, download, or save of an invoice PDF.
 * The first record for an invoice is the original; all subsequent records
 * have is_duplicate = true and their PDF displays the DUPLICATE watermark.
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $template_key
 * @property string $paper
 * @property string $action
 * @property bool $is_duplicate
 * @property string|null $path
 * @property int|null $size_bytes
 * @property string|null $sha256
 * @property int|null $generated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class InvoiceDocument extends Model
{
    protected $fillable = [
        'invoice_id', 'template_key', 'paper', 'action',
        'is_duplicate', 'path', 'size_bytes', 'sha256', 'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_duplicate' => 'boolean',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
