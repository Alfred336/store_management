<?php

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotations.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quotations.create');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Draft && $user->can('quotations.update');
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Draft && $user->can('quotations.delete');
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Draft && $user->can('quotations.send');
    }

    public function accept(User $user, Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Sent
            && ! $quotation->isExpired()
            && $user->can('quotations.update');
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Sent
            && ! $quotation->isExpired()
            && $user->can('quotations.update');
    }

    public function revise(User $user, Quotation $quotation): bool
    {
        return ($quotation->status === QuotationStatus::Sent
            || $quotation->status === QuotationStatus::Rejected
            || $quotation->isExpired())
            && $user->can('quotations.create');
    }

    public function convert(User $user, Quotation $quotation): bool
    {
        return $quotation->status->canConvert()
            && ! $quotation->isExpired()
            && $quotation->converted_invoice_id === null
            && $user->can('quotations.convert');
    }

    public function print(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.print');
    }

    public function approveDiscount(User $user): bool
    {
        return $user->can('quotations.approve_discount');
    }
}
