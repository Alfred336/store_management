<?php

namespace App\Livewire\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Livewire\Actions\Inventory\AdjustStock;
use App\Livewire\Actions\Inventory\ReceiveStock;
use App\Livewire\Actions\Inventory\TransferStock;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Stock on hand: per product and location, per batch for batch-tracked products. */
#[Title('Stock on Hand')]
class Index extends Component
{
    use WithPagination;

    // ─── Filters ──────────────────────────────────────────────────────────

    #[Url(as: 'q')]
    public string $search = '';

    public string $locationFilter = '';

    public string $categoryFilter = '';

    public bool $outOfStockOnly = false;

    public bool $lowStockOnly = false;

    public int $expiringDays = 0;

    // ─── Receive form ─────────────────────────────────────────────────────

    #[Locked]
    public ?int $receiveProductId = null;

    public string $receiveLocationId = '';

    public string $receiveUnitId = '';

    public string $receiveQuantity = '';

    public string $receiveBatchChoice = 'existing'; // 'existing' | 'new'

    public string $receiveBatchId = '';

    public string $receiveBatchNumber = '';

    public string $receiveBatchExpiry = '';

    public string $receiveReference = '';

    public string $receivePersonName = '';

    public string $receiveNotes = '';

    // ─── Adjust form ──────────────────────────────────────────────────────

    #[Locked]
    public ?int $adjustProductId = null;

    public string $adjustLocationId = '';

    #[Locked]
    public ?int $adjustBatchId = null;

    public string $adjustQuantity = '';

    public string $adjustReason = '';

    public string $adjustReference = '';

    // ─── Transfer form ────────────────────────────────────────────────────

    #[Locked]
    public ?int $transferProductId = null;

    public string $transferFromLocationId = '';

    public string $transferToLocationId = '';

    #[Locked]
    public ?int $transferBatchId = null;

    public string $transferQuantity = '';

    public string $transferReference = '';

    // ─── Mount ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('inventory.view'), 403);
    }

    // ─── Filter watchers ──────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOutOfStockOnly(): void
    {
        $this->resetPage();
    }

    public function updatedLowStockOnly(): void
    {
        $this->resetPage();
    }

    public function updatedExpiringDays(): void
    {
        $this->resetPage();
    }

    // ─── Receive ──────────────────────────────────────────────────────────

    public function openReceive(?int $productId = null, string $locationId = ''): void
    {
        abort_unless(auth()->user()?->can('inventory.receive'), 403);

        $this->resetReceiveForm();
        $this->receiveProductId = $productId;
        $this->receiveLocationId = $locationId;
        $this->dispatch('modal-show', name: 'receive-form');
    }

    public function receive(ReceiveStock $action): void
    {
        abort_unless(auth()->user()?->can('inventory.receive'), 403);

        $product = $this->receiveProductId !== null
            ? Product::query()->findOrFail($this->receiveProductId)
            : null;

        $batchId = null;
        $batchNumber = null;
        $batchExpiry = null;

        if ($product?->track_batches) {
            if ($this->receiveBatchChoice === 'existing') {
                $this->validate(['receiveBatchId' => ['required', 'integer', 'min:1']]);
                $batchId = (int) $this->receiveBatchId;
            } else {
                $this->validate(['receiveBatchNumber' => ['required', 'string', 'max:100']]);
                $batchNumber = trim($this->receiveBatchNumber);
                $batchExpiry = $this->receiveBatchExpiry !== '' ? $this->receiveBatchExpiry : null;
            }
        }

        $this->validate([
            'receiveProductId' => ['required', 'integer', 'min:1'],
            'receiveLocationId' => ['required', 'integer', 'min:1'],
            'receiveQuantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
        ]);

        try {
            $action([
                'product_id' => $this->receiveProductId,
                'location_id' => (int) $this->receiveLocationId,
                'unit_id' => $this->receiveUnitId !== '' ? (int) $this->receiveUnitId : null,
                'quantity' => $this->receiveQuantity,
                'batch_id' => $batchId,
                'batch_number' => $batchNumber,
                'batch_expiry' => $batchExpiry,
                'reference' => $this->receiveReference !== '' ? $this->receiveReference : null,
                'person_name' => $this->receivePersonName !== '' ? $this->receivePersonName : null,
                'reason' => $this->receiveNotes !== '' ? $this->receiveNotes : null,
            ]);

            $this->dispatch('modal-close', name: 'receive-form');
            $this->resetReceiveForm();
            Flux::toast(variant: 'success', text: 'Stock received successfully.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    private function resetReceiveForm(): void
    {
        $this->receiveProductId = null;
        $this->receiveLocationId = '';
        $this->receiveUnitId = '';
        $this->receiveQuantity = '';
        $this->receiveBatchChoice = 'existing';
        $this->receiveBatchId = '';
        $this->receiveBatchNumber = '';
        $this->receiveBatchExpiry = '';
        $this->receiveReference = '';
        $this->receivePersonName = '';
        $this->receiveNotes = '';
        $this->resetErrorBag();
    }

    // ─── Adjust ───────────────────────────────────────────────────────────

    public function openAdjust(int $productId, string $locationId, ?int $batchId = null): void
    {
        abort_unless(auth()->user()?->can('inventory.adjust'), 403);

        $this->resetAdjustForm();
        $this->adjustProductId = $productId;
        $this->adjustLocationId = $locationId;
        $this->adjustBatchId = $batchId;
        $this->dispatch('modal-show', name: 'adjust-form');
    }

    public function adjust(AdjustStock $action): void
    {
        abort_unless(auth()->user()?->can('inventory.adjust'), 403);

        $this->validate([
            'adjustProductId' => ['required', 'integer', 'min:1'],
            'adjustLocationId' => ['required', 'integer', 'min:1'],
            'adjustQuantity' => ['required', 'numeric', 'decimal:0,3', 'not_in:0,0.000'],
            'adjustReason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $action([
                'product_id' => $this->adjustProductId,
                'location_id' => (int) $this->adjustLocationId,
                'batch_id' => $this->adjustBatchId,
                'quantity' => $this->adjustQuantity,
                'reason' => $this->adjustReason,
                'reference' => $this->adjustReference !== '' ? $this->adjustReference : null,
                'person_name' => auth()->user()->name,
            ]);

            $this->dispatch('modal-close', name: 'adjust-form');
            $this->resetAdjustForm();
            Flux::toast(variant: 'success', text: 'Stock adjusted successfully.');
        } catch (InsufficientStockException $e) {
            $this->addError('adjustQuantity', "Insufficient stock: {$e->getMessage()}");
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $this->addError('adjustQuantity', 'An unexpected error occurred. Please try again.');
        }
    }

    private function resetAdjustForm(): void
    {
        $this->adjustProductId = null;
        $this->adjustLocationId = '';
        $this->adjustBatchId = null;
        $this->adjustQuantity = '';
        $this->adjustReason = '';
        $this->adjustReference = '';
        $this->resetErrorBag();
    }

    // ─── Transfer ─────────────────────────────────────────────────────────

    public function openTransfer(int $productId, string $fromLocationId, ?int $batchId = null): void
    {
        abort_unless(auth()->user()?->can('inventory.transfer'), 403);

        $this->resetTransferForm();
        $this->transferProductId = $productId;
        $this->transferFromLocationId = $fromLocationId;
        $this->transferBatchId = $batchId;
        $this->dispatch('modal-show', name: 'transfer-form');
    }

    public function transfer(TransferStock $action): void
    {
        abort_unless(auth()->user()?->can('inventory.transfer'), 403);

        $this->validate([
            'transferProductId' => ['required', 'integer', 'min:1'],
            'transferFromLocationId' => ['required', 'integer', 'min:1'],
            'transferToLocationId' => ['required', 'integer', 'min:1', 'different:transferFromLocationId'],
            'transferQuantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
        ]);

        try {
            $action([
                'product_id' => $this->transferProductId,
                'from_location_id' => (int) $this->transferFromLocationId,
                'to_location_id' => (int) $this->transferToLocationId,
                'batch_id' => $this->transferBatchId,
                'quantity' => $this->transferQuantity,
                'reference' => $this->transferReference !== '' ? $this->transferReference : null,
                'person_name' => auth()->user()->name,
            ]);

            $this->dispatch('modal-close', name: 'transfer-form');
            $this->resetTransferForm();
            Flux::toast(variant: 'success', text: 'Stock transferred successfully.');
        } catch (InsufficientStockException $e) {
            $this->addError('transferQuantity', "Insufficient stock: {$e->getMessage()}");
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $this->addError('transferQuantity', 'An unexpected error occurred. Please try again.');
        }
    }

    private function resetTransferForm(): void
    {
        $this->transferProductId = null;
        $this->transferFromLocationId = '';
        $this->transferToLocationId = '';
        $this->transferBatchId = null;
        $this->transferQuantity = '';
        $this->transferReference = '';
        $this->resetErrorBag();
    }

    // ─── Render ───────────────────────────────────────────────────────────

    public function render(): View
    {
        abort_unless(auth()->user()?->can('inventory.view'), 403);

        $levels = $this->buildQuery()->paginate(50);

        // Per-product alert badges — derived from the same scopes used everywhere else
        $productIds = $levels->pluck('product_id')->unique()->values()->all();
        $lowStockIds = Product::lowStock()->whereIn('id', $productIds)->pluck('id');
        $outOfStockIds = Product::outOfStock()->whereIn('id', $productIds)->pluck('id');

        $locations = Location::query()->where('is_active', true)->orderBy('name')->get();
        $categories = Category::query()->orderBy('name')->get();

        // Batches for the receive form's "existing batch" dropdown
        $receiveBatches = $this->receiveProductId !== null
            ? Batch::query()
                ->where('product_id', $this->receiveProductId)
                ->orderBy('batch_number')
                ->get()
            : collect();

        // Product units for the receive form's unit dropdown
        $receiveProduct = $this->receiveProductId !== null
            ? Product::query()->with(['baseUnit', 'productUnits.unit'])->find($this->receiveProductId)
            : null;

        $adjustProduct = $this->adjustProductId !== null
            ? Product::query()->with('baseUnit')->find($this->adjustProductId)
            : null;

        $transferProduct = $this->transferProductId !== null
            ? Product::query()->with('baseUnit')->find($this->transferProductId)
            : null;

        return view('livewire.inventory.index', compact(
            'levels', 'lowStockIds', 'outOfStockIds',
            'locations', 'categories',
            'receiveBatches', 'receiveProduct',
            'adjustProduct', 'transferProduct'
        ));
    }

    /** @return Builder<StockLevel> */
    private function buildQuery(): Builder
    {
        return StockLevel::query()
            ->select('stock_levels.*')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('batches', 'batches.id', '=', 'stock_levels.batch_id')
            ->join('locations', 'locations.id', '=', 'stock_levels.location_id')
            ->whereNull('products.deleted_at')
            ->with(['product.baseUnit', 'location', 'batch'])
            ->when($this->search !== '', function (Builder $q): void {
                $pattern = '%'.$this->search.'%';
                $q->where(function (Builder $q2) use ($pattern): void {
                    $q2->where('products.name', 'ILIKE', $pattern)
                        ->orWhere('products.sku', 'ILIKE', $pattern)
                        ->orWhere('products.barcode', 'ILIKE', $pattern);
                });
            })
            ->when($this->locationFilter !== '', fn (Builder $q) => $q->where('stock_levels.location_id', (int) $this->locationFilter))
            ->when($this->categoryFilter !== '', fn (Builder $q) => $q->where('products.category_id', (int) $this->categoryFilter))
            ->when($this->outOfStockOnly, fn (Builder $q) => $q
                ->whereIn('stock_levels.product_id', Product::outOfStock()->select('id'))
            )
            ->when($this->lowStockOnly, fn (Builder $q) => $q
                ->whereIn('stock_levels.product_id', Product::lowStock()->select('id'))
            )
            ->when($this->expiringDays > 0, fn (Builder $q) => $q
                ->whereNotNull('stock_levels.batch_id')
                ->whereNotNull('batches.expiry_date')
                ->whereRaw('batches.expiry_date BETWEEN ? AND ?', [
                    today()->toDateString(),
                    today()->addDays($this->expiringDays)->toDateString(),
                ])
            )
            ->orderBy('products.name')
            ->orderBy('locations.name')
            ->orderByRaw('batches.expiry_date ASC NULLS LAST');
    }
}
