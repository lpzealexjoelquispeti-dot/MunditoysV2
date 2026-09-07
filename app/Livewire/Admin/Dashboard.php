<?php

namespace App\Livewire\Admin;

use App\Services\DashboardService;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(DashboardService $dashboardService): View
    {
        return view('livewire.admin.dashboard', $dashboardService->obtenerResumen());
    }
}
