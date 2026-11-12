<?php

namespace App\Livewire\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteQuotationDraft
{
    public function __invoke(Quotation $quotation): void
    {
        Gate::authorize('delete', $quotation);

        DB::transaction(function () use ($quotation): void {
            $locked = Quotation::query()
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== QuotationStatus::Draft) {
                throw ValidationException::withMessages(['quotation' => 'Only draft quotations can be deleted.']);
            }

            $locked->items()->delete();
            $locked->delete();
        });
    }
}
