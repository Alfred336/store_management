<?php

namespace App\Livewire\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RejectQuotation
{
    public function __invoke(Quotation $quotation, string $reason = ''): Quotation
    {
        Gate::authorize('reject', $quotation);

        return DB::transaction(function () use ($quotation, $reason): Quotation {
            $locked = Quotation::query()
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== QuotationStatus::Sent) {
                throw ValidationException::withMessages(['quotation' => 'Only sent quotations can be rejected.']);
            }

            if ($locked->isExpired()) {
                throw ValidationException::withMessages(['quotation' => 'An expired quotation cannot be rejected. Please revise it first.']);
            }

            $locked->update([
                'status' => QuotationStatus::Rejected,
                'rejected_at' => now(),
                'reject_reason' => $reason !== '' ? $reason : null,
            ]);

            return $locked->fresh();
        });
    }
}
