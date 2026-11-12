<?php

namespace App\Services\Invoicing;

use App\Services\Settings\Settings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Allocate transaction-safe, rollback-safe document sequence numbers. */
class DocumentNumberingService
{
    public function __construct(private readonly Settings $settings) {}

    public function next(string $type, ?int $year = null): string
    {
        if (! in_array($type, ['invoice', 'receipt', 'payment', 'customer', 'quotation'], true)) {
            throw new InvalidArgumentException('Unsupported document sequence type.');
        }

        return DB::transaction(function () use ($type, $year): string {
            $displayYear = $year ?? (int) now()->format('Y');
            $resetYearly = (bool) $this->settings->get('numbering.reset_yearly', true);
            $sequenceYear = $resetYearly ? $displayYear : 0;
            $prefix = (string) $this->settings->get("numbering.{$type}_prefix", match ($type) {
                'invoice' => 'INV',
                'receipt' => 'RCT',
                'payment' => 'PAY',
                'customer' => 'CUS',
                'quotation' => 'QUO',
            });
            $digits = (int) $this->settings->get('numbering.digits', 6);
            $digits = max(1, min($digits, 12));

            DB::table('document_sequences')->insertOrIgnore([
                'type' => $type,
                'year' => $sequenceYear,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('type', $type)
                ->where('year', $sequenceYear)
                ->lockForUpdate()
                ->first();

            $next = (int) $sequence->last_number + 1;

            DB::table('document_sequences')
                ->where('type', $type)
                ->where('year', $sequenceYear)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return sprintf('%s-%d-%0'.$digits.'d', $prefix, $displayYear, $next);
        });
    }
}
