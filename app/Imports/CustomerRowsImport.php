<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/** Read customer rows by the documented, normalized heading names. */
class CustomerRowsImport implements ToCollection, WithHeadingRow
{
    /** @var Collection<int, array<array-key, mixed>> */
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function collection(Collection $collection): void
    {
        $this->rows = $collection
            ->map(fn (mixed $row): array => is_array($row) ? $row : $row->toArray())
            ->values();
    }
}
