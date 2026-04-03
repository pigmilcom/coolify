<div>
    <x-slot:title>
        Subscriptions | Coolify
    </x-slot>

    <div class="flex items-start gap-2 pb-6">
        <div>
            <h1 class="pb-2">Subscriptions</h1>
            <div class="subtitle">Manage subscription plans, team assignments, and payment history.</div>
        </div>
    </div>

    <div x-data="{ tab: @entangle('activeTab') }" class="flex flex-col gap-6">
        {{-- Tab Navigation --}}
        <div class="flex gap-1 border-b dark:border-coolgray-200">
            <button @click="tab = 'plans'" :class="tab === 'plans' && 'border-b-2 border-coollabs dark:border-coollabs font-semibold'" class="px-4 py-2 text-sm transition-colors">
                Plans
            </button>
            <button @click="tab = 'users'" :class="tab === 'users' && 'border-b-2 border-coollabs dark:border-coollabs font-semibold'" class="px-4 py-2 text-sm transition-colors">
                Team Subscriptions
            </button>
            <button @click="tab = 'payments'" :class="tab === 'payments' && 'border-b-2 border-coollabs dark:border-coollabs font-semibold'" class="px-4 py-2 text-sm transition-colors">
                Payment History
            </button>
        </div>

        {{-- Plans Tab --}}
        <div x-show="tab === 'plans'">
            <div class="grid gap-6 xl:grid-cols-2">
                {{-- Plan Form --}}
                <div class="coolbox">
                    <h3 class="pb-4">{{ $editingPlanId ? 'Edit Plan' : 'New Plan' }}</h3>
                    <div class="flex flex-col gap-3">
                        <x-forms.input label="Name" wire:model="planName" id="planName" placeholder="e.g. Pro, Enterprise" required />
                        <x-forms.textarea label="Description" wire:model="planDescription" id="planDescription" rows="2" placeholder="Short description of this plan" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-forms.select label="Billing Cycle" wire:model.live="planBillingCycle" id="planBillingCycle">
                                <option value="free">Free</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                                <option value="lifetime">Lifetime</option>
                            </x-forms.select>
                            @if ($planBillingCycle !== 'free')
                                <x-forms.input label="Price (USD)" wire:model="planPrice" id="planPrice" type="number" min="0" step="0.01" placeholder="0.00" />
                            @endif
                        </div>
                        <x-forms.textarea label="Features (one per line)" wire:model="planFeatures" id="planFeatures" rows="4" placeholder="Unlimited deployments&#10;Custom domains&#10;Priority support" />
                        <div class="flex gap-4 items-center">
                            <x-forms.input label="Sort Order" wire:model="planSortOrder" id="planSortOrder" type="number" min="0" />
                            <div class="flex items-center gap-2 pt-6">
                                <input type="checkbox" wire:model="planIsActive" id="planIsActive" class="checkbox">
                                <label for="planIsActive" class="text-sm">Active</label>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <x-forms.button wire:click="savePlan" class="btn">
                                {{ $editingPlanId ? 'Update Plan' : 'Create Plan' }}
                            </x-forms.button>
                            @if ($editingPlanId)
                                <x-forms.button wire:click="cancelEditPlan" class="btn btn-error">Cancel</x-forms.button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Plans List --}}
                <div class="flex flex-col gap-3">
                    @forelse ($plans as $plan)
                        <div class="coolbox">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex flex-col gap-1 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold">{{ $plan->name }}</span>
                                        @if (! $plan->is_active)
                                            <span class="text-xs px-2 py-0.5 rounded bg-neutral-200 dark:bg-coolgray-200 text-neutral-500">Inactive</span>
                                        @endif
                                    </div>
                                    @if ($plan->description)
                                        <p class="text-sm text-neutral-500">{{ $plan->description }}</p>
                                    @endif
                                    <div class="text-sm font-medium text-coollabs">{{ $plan->formattedPrice() }}</div>
                                    @if ($plan->features)
                                        <ul class="text-sm text-neutral-500 list-disc list-inside mt-1">
                                            @foreach ($plan->features as $feature)
                                                <li>{{ $feature }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <x-forms.button wire:click="editPlan({{ $plan->id }})" class="btn btn-xs">Edit</x-forms.button>
                                    <x-modal-confirmation title="Delete Plan?" isHighlighted buttonTitle="Delete"
                                        submitAction="deletePlan({{ $plan->id }})"
                                        :actions="['This plan will be permanently deleted.']"
                                        confirmationText="{{ $plan->name }}"
                                        confirmationLabel="Enter plan name to confirm"
                                        shortConfirmationLabel="Plan Name"
                                        :confirmWithPassword="false"
                                        step2ButtonText="Delete Plan" />
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="coolbox text-neutral-500">No plans created yet. Create your first plan above.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Team Subscriptions Tab --}}
        <div x-show="tab === 'users'">
            <div class="grid gap-6 xl:grid-cols-2">
                {{-- Assign Form --}}
                <div class="coolbox">
                    <h3 class="pb-4">Assign Plan to Team</h3>
                    <div class="flex flex-col gap-3">
                        <x-forms.select label="Team" wire:model.live="assignTeamId" id="assignTeamId" required>
                            <option value="">Select a team...</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </x-forms.select>
                        <x-forms.select label="Plan" wire:model="assignPlanId" id="assignPlanId" required>
                            <option value="">Select a plan...</option>
                            @foreach ($plans->where('is_active', true) as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->formattedPrice() }})</option>
                            @endforeach
                        </x-forms.select>
                        <x-forms.select label="Status" wire:model="assignStatus" id="assignStatus">
                            <option value="active">Active</option>
                            <option value="trial">Trial</option>
                        </x-forms.select>
                        <x-forms.input label="Expires At (optional)" wire:model="assignExpiresAt" id="assignExpiresAt" type="datetime-local" />
                        <x-forms.textarea label="Notes (optional)" wire:model="assignNotes" id="assignNotes" rows="2" />
                        <x-forms.button wire:click="assignPlan" class="btn pt-2">Assign Plan</x-forms.button>
                    </div>
                </div>

                {{-- Team Plan List --}}
                <div class="flex flex-col gap-3">
                    @forelse ($teamPlans as $teamPlan)
                        <div class="coolbox">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex flex-col gap-1">
                                    <div class="font-semibold">{{ $teamPlan->team->name }}</div>
                                    <div class="text-sm">Plan: <span class="font-medium">{{ $teamPlan->plan->name }}</span></div>
                                    <div class="flex items-center gap-2">
                                        <span @class([
                                            'text-xs px-2 py-0.5 rounded font-medium',
                                            'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $teamPlan->status === 'active',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' => $teamPlan->status === 'trial',
                                            'bg-neutral-200 text-neutral-500 dark:bg-coolgray-200' => in_array($teamPlan->status, ['cancelled', 'expired']),
                                        ])>{{ ucfirst($teamPlan->status) }}</span>
                                        @if ($teamPlan->expires_at)
                                            <span class="text-xs text-neutral-500">Expires {{ $teamPlan->expires_at->format('M d, Y') }}</span>
                                        @endif
                                    </div>
                                    @if ($teamPlan->notes)
                                        <p class="text-xs text-neutral-500">{{ $teamPlan->notes }}</p>
                                    @endif
                                </div>
                                @if ($teamPlan->isActive())
                                    <x-modal-confirmation title="Cancel Team Plan?" isHighlighted buttonTitle="Cancel Plan"
                                        submitAction="cancelTeamPlan({{ $teamPlan->id }})"
                                        :actions="['The team will no longer have an active plan.']"
                                        confirmationText="{{ $teamPlan->team->name }}"
                                        confirmationLabel="Enter team name to confirm"
                                        shortConfirmationLabel="Team Name"
                                        :confirmWithPassword="false"
                                        step2ButtonText="Cancel Plan" />
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="coolbox text-neutral-500">No team plan assignments yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Payment History Tab --}}
        <div x-show="tab === 'payments'">
            <div class="grid gap-6 xl:grid-cols-2">
                {{-- Payment Form --}}
                <div class="coolbox">
                    <h3 class="pb-4">Record Payment</h3>
                    <div class="flex flex-col gap-3">
                        <x-forms.select label="Team" wire:model.live="paymentTeamId" id="paymentTeamId" required>
                            <option value="">Select a team...</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </x-forms.select>
                        @if ($paymentTeamId)
                            <x-forms.select label="Related Plan Assignment (optional)" wire:model="paymentTeamPlanId" id="paymentTeamPlanId">
                                <option value="">None</option>
                                @foreach ($teamPlans->where('team_id', $paymentTeamId) as $tp)
                                    <option value="{{ $tp->id }}">{{ $tp->plan->name }} ({{ ucfirst($tp->status) }})</option>
                                @endforeach
                            </x-forms.select>
                        @endif
                        <div class="grid grid-cols-2 gap-3">
                            <x-forms.input label="Amount" wire:model="paymentAmount" id="paymentAmount" type="number" min="0" step="0.01" placeholder="0.00" required />
                            <x-forms.input label="Currency" wire:model="paymentCurrency" id="paymentCurrency" placeholder="USD" maxlength="3" />
                        </div>
                        <x-forms.select label="Status" wire:model="paymentStatus" id="paymentStatus">
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </x-forms.select>
                        <x-forms.input label="Paid At (optional)" wire:model="paymentPaidAt" id="paymentPaidAt" type="datetime-local" />
                        <x-forms.textarea label="Description (optional)" wire:model="paymentDescription" id="paymentDescription" rows="2" />
                        <x-forms.button wire:click="savePayment" class="btn pt-2">Record Payment</x-forms.button>
                    </div>
                </div>

                {{-- Payment List --}}
                <div class="flex flex-col gap-3">
                    @forelse ($payments as $payment)
                        <div class="coolbox">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex flex-col gap-1">
                                    <div class="font-semibold">{{ $payment->team->name }}</div>
                                    @if ($payment->teamPlan)
                                        <div class="text-sm text-neutral-500">Plan: {{ $payment->teamPlan->plan->name }}</div>
                                    @endif
                                    <div class="text-sm">
                                        <span class="font-medium">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span @class([
                                            'text-xs px-2 py-0.5 rounded font-medium',
                                            'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $payment->status === 'paid',
                                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' => $payment->status === 'pending',
                                            'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' => $payment->status === 'failed',
                                            'bg-neutral-200 text-neutral-500 dark:bg-coolgray-200' => $payment->status === 'refunded',
                                        ])>{{ ucfirst($payment->status) }}</span>
                                        @if ($payment->paid_at)
                                            <span class="text-xs text-neutral-500">{{ $payment->paid_at->format('M d, Y') }}</span>
                                        @endif
                                    </div>
                                    @if ($payment->description)
                                        <p class="text-xs text-neutral-500">{{ $payment->description }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="coolbox text-neutral-500">No payment records yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
