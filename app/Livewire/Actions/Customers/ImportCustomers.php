<?php

namespace App\Livewire\Actions\Customers;

use App\Imports\CustomerRowsImport;
use App\Models\Customer;
use App\Services\Invoicing\DocumentNumberingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/** Import customer records atomically from a CSV or Excel upload. */
class ImportCustomers
{
    public function __invoke(UploadedFile $file): int
    {
        Gate::authorize('create', Customer::class);
        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'mimes:csv,txt,xls,xlsx', 'max:10240'],
        ])->validate();

        $import = new CustomerRowsImport;
        Excel::import($import, $file);

        return DB::transaction(function () use ($import): int {
            $count = 0;

            foreach ($import->rows as $index => $row) {
                $normalized = $this->normalizeRow($row);
                $validated = Validator::make($normalized, [
                    'name' => ['required', 'string', 'max:255'],
                    'type' => ['required', Rule::in(['individual', 'business'])],
                    'phone' => ['nullable', 'string', 'max:50'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'address' => ['nullable', 'string', 'max:2000'],
                    'tin' => ['nullable', 'string', 'max:50'],
                    'notes' => ['nullable', 'string', 'max:5000'],
                    'credit_limit' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
                    'payment_terms_days' => ['required', 'integer', 'between:0,365'],
                    'is_active' => ['required', 'boolean'],
                ])->validate();

                if (strcasecmp(trim($validated['name']), 'Walk-in Customer') === 0) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.name" => 'The Walk-in Customer name is reserved for the protected system record.',
                    ]);
                }

                Customer::query()->create([
                    'code' => app(DocumentNumberingService::class)->next('customer'),
                    'name' => trim($validated['name']),
                    'type' => $validated['type'],
                    'phone' => $validated['phone'] ?: null,
                    'email' => $validated['email'] ? strtolower($validated['email']) : null,
                    'address' => $validated['address'] ?: null,
                    'tin' => $validated['tin'] ?: null,
                    'notes' => $validated['notes'] ?: null,
                    'credit_limit' => (string) $validated['credit_limit'],
                    'payment_terms_days' => (int) $validated['payment_terms_days'],
                    'is_active' => (bool) $validated['is_active'],
                    'is_walk_in' => false,
                ]);

                $count++;
            }

            return $count;
        });
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $creditLimit = $row['credit_limit'] ?? '0.00';

        // Spreadsheet numeric cells may be represented as floats by the reader; stringify without calculations.
        if (is_int($creditLimit) || is_float($creditLimit)) {
            $creditLimit = (string) $creditLimit;
        }

        $active = $row['is_active'] ?? true;

        if (is_string($active)) {
            $active = filter_var($active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return [
            'name' => $this->stringValue($row['name'] ?? null),
            'type' => $this->stringValue($row['type'] ?? 'individual'),
            'phone' => $this->stringValue($row['phone'] ?? null),
            'email' => $this->stringValue($row['email'] ?? null),
            'address' => $this->stringValue($row['address'] ?? null),
            'tin' => $this->stringValue($row['tin'] ?? null),
            'notes' => $this->stringValue($row['notes'] ?? null),
            'credit_limit' => $creditLimit,
            'payment_terms_days' => $row['payment_terms_days'] ?? 0,
            'is_active' => $active,
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
