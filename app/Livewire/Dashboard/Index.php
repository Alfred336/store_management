<?php

namespace App\Livewire\Dashboard;

use App\Services\Access\LocationAccess;
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
        abort_unless(auth()->user()?->can('dashboard.view'), 403);
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

    // ─── Render ───────────────────────────────────────────────────────────

    public function render(): View
    {
        abort_unless(auth()->user()?->can('dashboard.view'), 403);

        $svc = app(DashboardService::class);
        $access = app(LocationAccess::class);
        $user = auth()->user();

        $allowedIds = $access->allowedLocationIds($user);

        // Guard URL-forged locationId: if user has no access to that location, ignore it.
        $rawLocId = $this->locationId !== '' ? (int) $this->locationId : null;
        $locId = null;
        if ($rawLocId !== null) {
            if ($allowedIds === null || in_array($rawLocId, $allowedIds, true)) {
                $locId = $rawLocId;
            }
            // If user doesn't have access, $locId stays null and the allowedIds guard still applies.
        }

        $userId = (int) auth()->id();
        $cashier = $this->isCashier();
        $period = in_array($this->period, ['today', '7d', 'month'], true) ? $this->period : 'today';

        $data = [];

        if ($this->canViewSales() || $cashier) {
            $data['salesKpis'] = $svc->salesKpis($period, $locId, $cashier, $userId, $allowedIds);
            $data['receivablesKpis'] = $cashier ? null : $svc->receivablesKpis($locId, $allowedIds);
            $data['quotationKpis'] = $cashier ? null : $svc->quotationKpis($locId, $allowedIds);
            $data['recentInvoices'] = $svc->recentInvoices($locId, $cashier, $userId, $allowedIds);
            $data['topProducts'] = $cashier ? collect() : $svc->topProducts($period, $locId, $allowedIds);
            $data['overdueInvoices'] = $cashier ? collect() : $svc->overdueInvoices($locId, $allowedIds);
            $data['salesByDayChart'] = $cashier ? ['labels' => [], 'datasets' => []] : $svc->salesByDay($locId, $allowedIds);
            $data['salesByCatChart'] = $cashier ? ['labels' => [], 'datasets' => []] : $svc->salesByCategory($period, $locId, $allowedIds);
            $data['paymentsByMethod'] = $cashier ? ['labels' => [], 'datasets' => []] : $svc->paymentsByMethod($period, $locId, $allowedIds);
        }

        if ($this->canViewInventory()) {
            $data['inventoryKpis'] = $svc->inventoryKpis($allowedIds);
            $data['urgentItems'] = $svc->urgentInventoryItems($allowedIds);
        }

        $data['locations'] = $access->allowedLocations($user);
        $data['period'] = $period;
        $data['isCashier'] = $cashier;

        return view('livewire.dashboard.index', $data);
    }
}
