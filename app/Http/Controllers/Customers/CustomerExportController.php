<?php

namespace App\Http\Controllers\Customers;

use App\Exports\CustomersExport;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Download the authorized customer catalog as XLSX or CSV. */
class CustomerExportController
{
    public function __invoke(Request $request, string $format): BinaryFileResponse
    {
        Gate::authorize('viewAny', Customer::class);
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $writer = $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;

        return Excel::download(new CustomersExport, "customers.{$format}", $writer);
    }
}
