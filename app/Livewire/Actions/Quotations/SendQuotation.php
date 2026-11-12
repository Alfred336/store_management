<?php

namespace App\Livewire\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Services\Invoicing\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Transition a draft quotation to "sent":
 *  - Assigns a QUO-… sequence number.
 *  - Snapshots customer/prospect details.
 *  - Marks the quotation immutable.
 */
class SendQuotation
{
    public function __construct(
        private readonly DocumentNumberingService $numberingService,
    ) {}

    public function __invoke(Quotation $quotation): Quotation
    {
        Gate::authorize('send', $quotation);

        return DB::transaction(function () use ($quotation): Quotation {
            $locked = Quotation::query()
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== QuotationStatus::Draft) {
                throw ValidationException::withMessages(['quotation' => 'Only draft quotations can be sent.']);
            }

            if ($locked->items()->count() === 0) {
                throw ValidationException::withMessages(['items' => 'A quotation must contain at least one item before sending.']);
            }

            $number = $this->numberingService->next('quotation');

            // Build snapshot
            $snapshotName = null;
            $snapshotPhone = null;
            $snapshotAddr = null;

            if ($locked->customer_id !== null) {
                $customer = $locked->customer()->lockForUpdate()->first();
                if (! $customer || ! $customer->is_active) {
                    throw ValidationException::withMessages(['customer_id' => 'The customer is no longer active.']);
                }
                $snapshotName = $customer->name;
                $snapshotPhone = $customer->phone;
                $snapshotAddr = $customer->address;
            } else {
                $snapshotName = $locked->prospect_name;
                $snapshotPhone = $locked->prospect_phone;
                $snapshotAddr = $locked->prospect_address;
            }

            $locked->update([
                'number' => $number,
                'status' => QuotationStatus::Sent,
                'customer_name' => $snapshotName,
                'customer_phone' => $snapshotPhone,
                'customer_address' => $snapshotAddr,
                'sent_by' => auth()->id(),
                'sent_at' => now(),
            ]);

            return $locked->fresh(['items.unit', 'customer', 'location']);
        });
    }
}
