<?php

namespace App\Livewire\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AcceptQuotation
{
    public function __invoke(Quotation $quotation): Quotation
    {
        Gate::authorize('accept', $quotation);

        return DB::transaction(function () use ($quotation): Quotation {
            $locked = Quotation::query()
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== QuotationStatus::Sent) {
                throw ValidationException::withMessages(['quotation' => 'Only sent quotations can be accepted.']);
            }

            if ($locked->isExpired()) {
                throw ValidationException::withMessages(['quotation' => 'An expired quotation cannot be accepted. Please revise it first.']);
            }

            $locked->update([
                'status' => QuotationStatus::Accepted,
                'accepted_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
