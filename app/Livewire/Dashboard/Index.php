<?php

namespace App\Livewire\Dashboard;

use App\Models\Location;
use App\Services\Dashboard\DashboardService;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Main dashboard: sales KPIs, inventory KPIs, charts, role-based sections. */
#[Title('Dashboard')]
class Index extends Component
{
    #[Url(as: 'period')]
    public string $period = 'today';

    #[Url(as: 'location')]
    public string $locationId = '';

    public function mount(): void
    {
        // Any authenticated user may visit the dashboard; sections are gated by permission.
        abort_unless(auth()->check(), 403);
    }

    // ─── Computed helpers ─────────────────────────────────────────────────

    #[Computed]
    public function canViewSales(): bool
    {
        return (bool) (auth()->user()?->can('invoices.view') || auth()->user()?->can('reports.view'));
    }

    #[Computed]
    public function canViewInventory(): bool
    {
        return (bool) auth()->user()?->can('inventory.view');
    }

    #[Computed]
    public function isCashier(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->can('pos.use')
            && ! $user->can('invoices.view')
            && ! $user->can('reports.view')
            && ! $user->can('inventory.view');
    }

    private function resolvedLocationId(): ?int
    {
        return $this->locationId !== '' ? (int) $this->locationId : null;
    }

    // ─── Render ───────────────────────────────────────────────────────────

    public function render(): View
    {
        abort_unless(auth()->check(), 403);

        $svc = app(DashboardService::class);
        $locId = $this->resolvedLocationId();
        $userId = (int) auth()->id();
        $cashier = $this->isCashier();
        $period = in_array($this->period, ['today', '7d', 'month'], true) ? $this->period : 'today';

        $data = [];

        if ($this->canViewSales() || $cashier) {
            $data['salesKpis'] = $svc->salesKpis($period, $locId, $cashier, $userId);
            $data['receivablesKpis'] = $cashier ? null : $svc->receivablesKpis($locId);
            $data['quotationKpis'] = $cashier ? null : $svc->quotationKpis($locId);
            $data['recentInvoices'] = $svc->recentInvoices($locId, $cashier, $userId);
            $data['topProducts'] = $cashier ? collect() : $svc->topProducts($period, $locId);
            $data['overdueInvoices'] = $cashier ? collect() : $svc->overdueInvoices($locId);
            $data['salesByDayChart'] = $cashier ? ['labels' => [], 'datasets' => []] : $svc->salesByDay($locId);
            $data['salesByCatChart'] = $cashier ? ['labels' => [], 'datasets' => []] : $svc->salesByCategory($period, $locId);
            $data['paymentsByMethod'] = $cashier ? ['labels' => [], 'datasets' => []] : $svc->paymentsByMethod($period, $locId);
        }

        if ($this->canViewInventory()) {
            $data['inventoryKpis'] = $svc->inventoryKpis();
            $data['urgentItems'] = $svc->urgentInventoryItems();
        }

        $data['locations'] = Location::query()->where('is_active', true)->orderBy('name')->get();
        $data['period'] = $period;
        $data['isCashier'] = $cashier;

        return view('livewire.dashboard.index', $data);
    }
}
