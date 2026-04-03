<?php

namespace App\Livewire\Subscriptions;

use App\Models\PaymentHistory;
use App\Models\Plan;
use App\Models\Team;
use App\Models\TeamPlan;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Subscriptions | Coolify')]
class Index extends Component
{
    public string $activeTab = 'plans';

    // Plan form
    public ?int $editingPlanId = null;

    public string $planName = '';

    public string $planDescription = '';

    public string $planPrice = '0';

    public string $planBillingCycle = 'free';

    public string $planFeatures = '';

    public bool $planIsActive = true;

    public int $planSortOrder = 0;

    // Team plan assignment form
    public ?int $assignTeamId = null;

    public ?int $assignPlanId = null;

    public string $assignStatus = 'active';

    public ?string $assignExpiresAt = null;

    public string $assignNotes = '';

    // Payment form
    public ?int $paymentTeamId = null;

    public ?int $paymentTeamPlanId = null;

    public string $paymentAmount = '';

    public string $paymentCurrency = 'USD';

    public string $paymentStatus = 'paid';

    public string $paymentDescription = '';

    public ?string $paymentPaidAt = null;

    #[Locked]
    public $plans;

    #[Locked]
    public $teams;

    #[Locked]
    public $teamPlans;

    #[Locked]
    public $payments;

    public function mount(): void
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        $this->plans = Plan::orderBy('sort_order')->orderBy('name')->get();
        $this->teams = Team::orderBy('name')->get();
        $this->teamPlans = TeamPlan::with(['team', 'plan'])->latest()->get();
        $this->payments = PaymentHistory::with(['team', 'teamPlan.plan'])->latest()->get();
    }

    public function savePlan(): void
    {
        $this->validate([
            'planName' => 'required|string|max:255',
            'planDescription' => 'nullable|string',
            'planPrice' => 'required|numeric|min:0',
            'planBillingCycle' => 'required|in:free,monthly,yearly,lifetime',
            'planFeatures' => 'nullable|string',
            'planIsActive' => 'boolean',
            'planSortOrder' => 'integer|min:0',
        ]);

        $features = collect(explode("\n", $this->planFeatures))
            ->map(fn ($f) => trim($f))
            ->filter()
            ->values()
            ->toArray();

        $data = [
            'name' => $this->planName,
            'description' => $this->planDescription ?: null,
            'price' => $this->planBillingCycle === 'free' ? 0 : $this->planPrice,
            'billing_cycle' => $this->planBillingCycle,
            'features' => $features ?: null,
            'is_active' => $this->planIsActive,
            'sort_order' => $this->planSortOrder,
        ];

        if ($this->editingPlanId) {
            Plan::findOrFail($this->editingPlanId)->update($data);
            $this->dispatch('success', 'Plan updated successfully.');
        } else {
            Plan::create($data);
            $this->dispatch('success', 'Plan created successfully.');
        }

        $this->resetPlanForm();
        $this->loadData();
    }

    public function editPlan(int $planId): void
    {
        $plan = Plan::findOrFail($planId);
        $this->editingPlanId = $plan->id;
        $this->planName = $plan->name;
        $this->planDescription = $plan->description ?? '';
        $this->planPrice = (string) $plan->price;
        $this->planBillingCycle = $plan->billing_cycle;
        $this->planFeatures = implode("\n", $plan->features ?? []);
        $this->planIsActive = $plan->is_active;
        $this->planSortOrder = $plan->sort_order;
        $this->activeTab = 'plans';
    }

    public function deletePlan(int $planId): void
    {
        $plan = Plan::findOrFail($planId);
        if ($plan->teamPlans()->exists()) {
            $this->dispatch('error', 'Cannot delete a plan that has active team assignments.');

            return;
        }

        $plan->delete();
        $this->dispatch('success', 'Plan deleted.');
        $this->loadData();
    }

    public function cancelEditPlan(): void
    {
        $this->resetPlanForm();
    }

    private function resetPlanForm(): void
    {
        $this->editingPlanId = null;
        $this->planName = '';
        $this->planDescription = '';
        $this->planPrice = '0';
        $this->planBillingCycle = 'free';
        $this->planFeatures = '';
        $this->planIsActive = true;
        $this->planSortOrder = 0;
    }

    public function assignPlan(): void
    {
        $this->validate([
            'assignTeamId' => 'required|exists:teams,id',
            'assignPlanId' => 'required|exists:plans,id',
            'assignStatus' => 'required|in:trial,active,cancelled,expired',
            'assignExpiresAt' => 'nullable|date',
            'assignNotes' => 'nullable|string',
        ]);

        // Cancel any existing active/trial plan for this team
        TeamPlan::where('team_id', $this->assignTeamId)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        TeamPlan::create([
            'team_id' => $this->assignTeamId,
            'plan_id' => $this->assignPlanId,
            'status' => $this->assignStatus,
            'starts_at' => now(),
            'expires_at' => $this->assignExpiresAt ?: null,
            'notes' => $this->assignNotes ?: null,
        ]);

        $this->dispatch('success', 'Plan assigned to team successfully.');
        $this->resetAssignForm();
        $this->loadData();
    }

    public function cancelTeamPlan(int $teamPlanId): void
    {
        TeamPlan::findOrFail($teamPlanId)->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->dispatch('success', 'Team plan cancelled.');
        $this->loadData();
    }

    private function resetAssignForm(): void
    {
        $this->assignTeamId = null;
        $this->assignPlanId = null;
        $this->assignStatus = 'active';
        $this->assignExpiresAt = null;
        $this->assignNotes = '';
    }

    public function savePayment(): void
    {
        $this->validate([
            'paymentTeamId' => 'required|exists:teams,id',
            'paymentTeamPlanId' => 'nullable|exists:team_plans,id',
            'paymentAmount' => 'required|numeric|min:0',
            'paymentCurrency' => 'required|string|size:3',
            'paymentStatus' => 'required|in:paid,pending,failed,refunded',
            'paymentDescription' => 'nullable|string',
            'paymentPaidAt' => 'nullable|date',
        ]);

        PaymentHistory::create([
            'team_id' => $this->paymentTeamId,
            'team_plan_id' => $this->paymentTeamPlanId ?: null,
            'amount' => $this->paymentAmount,
            'currency' => strtoupper($this->paymentCurrency),
            'status' => $this->paymentStatus,
            'description' => $this->paymentDescription ?: null,
            'paid_at' => $this->paymentStatus === 'paid' ? ($this->paymentPaidAt ?: now()) : null,
        ]);

        $this->dispatch('success', 'Payment recorded successfully.');
        $this->resetPaymentForm();
        $this->loadData();
    }

    private function resetPaymentForm(): void
    {
        $this->paymentTeamId = null;
        $this->paymentTeamPlanId = null;
        $this->paymentAmount = '';
        $this->paymentCurrency = 'USD';
        $this->paymentStatus = 'paid';
        $this->paymentDescription = '';
        $this->paymentPaidAt = null;
    }

    public function render()
    {
        return view('livewire.subscriptions.index');
    }
}
