<?php

namespace App\Livewire\Plan;

use App\Models\Plan;
use App\Models\TeamPlan;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Plan | Coolify')]
class Show extends Component
{
    #[Locked]
    public ?TeamPlan $currentTeamPlan = null;

    #[Locked]
    public $availablePlans;

    public function mount(): void
    {
        $this->currentTeamPlan = currentTeam()
            ->teamPlans()
            ->with('plan')
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->first();

        $this->availablePlans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();
    }

    public function render()
    {
        return view('livewire.plan.show');
    }
}
