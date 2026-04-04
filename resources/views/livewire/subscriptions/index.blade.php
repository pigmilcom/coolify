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
        <div class="flex gap-1 border-b dark:border-coolgray-200 overflow-x-auto scrollbar">
            <button @click="tab = 'plans'" :class="tab === 'plans' && 'border-b-2 border-coollabs dark:border-coollabs font-semibold'" class="px-4 py-2 text-sm transition-colors whitespace-nowrap shrink-0">
                Plans
            </button>
            <button @click="tab = 'users'" :class="tab === 'users' && 'border-b-2 border-coollabs dark:border-coollabs font-semibold'" class="px-4 py-2 text-sm transition-colors whitespace-nowrap shrink-0">
                Team Subscriptions
            </button>
            <button @click="tab = 'payments'" :class="tab === 'payments' && 'border-b-2 border-coollabs dark:border-coollabs font-semibold'" class="px-4 py-2 text-sm transition-colors whitespace-nowrap shrink-0">
                Payment History
            </button>
            <button @click="tab = 'api'" :class="tab === 'api' && 'border-b-2 border-coollabs dark:border-coollabs font-semibold'" class="px-4 py-2 text-sm transition-colors whitespace-nowrap shrink-0">
                API Access
            </button>
        </div>

        {{-- Plans Tab --}}
        <div x-show="tab === 'plans'">
            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Plan Form --}}
                <div class="coolbox">
                    <h3 class="pb-4">{{ $editingPlanId ? 'Edit Plan' : 'New Plan' }}</h3>
                    <div class="flex flex-col gap-3">
                        <x-forms.input label="Name" wire:model="planName" id="planName" placeholder="e.g. Pro, Enterprise" required />
                        <x-forms.textarea label="Description" wire:model="planDescription" id="planDescription" rows="2" placeholder="Short description of this plan" />
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <x-forms.input label="Resources Limit" wire:model="planResourcesLimit" id="planResourcesLimit" type="number" min="0" placeholder="Unlimited" />
                            <x-forms.input label="Projects Limit" wire:model="planProjectsLimit" id="planProjectsLimit" type="number" min="0" placeholder="Unlimited" />
                            <x-forms.input label="Bandwidth Limit (GB)" wire:model="planBandwidthLimit" id="planBandwidthLimit" type="number" min="0" placeholder="Unlimited" />
                            <x-forms.input label="Storage Limit (GB)" wire:model="planStorageLimit" id="planStorageLimit" type="number" min="0" placeholder="Unlimited" />
                            <x-forms.input label="Team Members Limit" wire:model="planTeamMembersLimit" id="planTeamMembersLimit" type="number" min="0" placeholder="Unlimited" />
                        </div>
                        <div class="flex flex-wrap gap-4 items-center">
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
                                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-neutral-500 mt-2 sm:grid-cols-3">
                                        <div><dt class="inline font-medium">Resources:</dt> <dd class="inline">{{ $plan->resources_limit !== null ? $plan->resources_limit : 'Unlimited' }}</dd></div>
                                        <div><dt class="inline font-medium">Projects:</dt> <dd class="inline">{{ $plan->projects_limit !== null ? $plan->projects_limit : 'Unlimited' }}</dd></div>
                                        <div><dt class="inline font-medium">Bandwidth:</dt> <dd class="inline">{{ $plan->bandwidth_limit !== null ? $plan->bandwidth_limit.' GB' : 'Unlimited' }}</dd></div>
                                        <div><dt class="inline font-medium">Storage:</dt> <dd class="inline">{{ $plan->storage_limit !== null ? $plan->storage_limit.' GB' : 'Unlimited' }}</dd></div>
                                        <div><dt class="inline font-medium">Team Members:</dt> <dd class="inline">{{ $plan->team_members_limit !== null ? $plan->team_members_limit : 'Unlimited' }}</dd></div>
                                    </dl>
                                </div>
                                <div class="flex flex-col gap-2 shrink-0 sm:flex-row">
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
            <div class="grid gap-6 lg:grid-cols-2">
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

        {{-- API Access Tab --}}
        <div x-show="tab === 'api'">
            <div class="flex flex-col gap-6">
                {{-- Authentication --}}
                <div class="coolbox">
                    <h3 class="pb-1">Authentication</h3>
                    <p class="text-sm text-neutral-500 pb-4">All requests require a Sanctum API token with <code class="text-xs bg-neutral-100 dark:bg-coolgray-300 px-1.5 py-0.5 rounded">write</code> ability (or <code class="text-xs bg-neutral-100 dark:bg-coolgray-300 px-1.5 py-0.5 rounded">root</code>) passed as a Bearer token. GET endpoints require <code class="text-xs bg-neutral-100 dark:bg-coolgray-300 px-1.5 py-0.5 rounded">read</code> or <code class="text-xs bg-neutral-100 dark:bg-coolgray-300 px-1.5 py-0.5 rounded">root</code>.</p>
                    <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">Authorization: Bearer &lt;your-api-token&gt;
Content-Type: application/json</pre>
                </div>

                {{-- Endpoints Table --}}
                <div class="coolbox overflow-x-auto">
                    <h3 class="pb-4">Endpoints</h3>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-coolgray-200 text-left text-xs text-neutral-500 uppercase tracking-wide">
                                <th class="pb-2 pr-4 font-medium">Method</th>
                                <th class="pb-2 pr-4 font-medium">Endpoint</th>
                                <th class="pb-2 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-coolgray-200">
                            <tr>
                                <td class="py-3 pr-4"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">POST</span></td>
                                <td class="py-3 pr-4 font-mono text-xs">/api/v1/subscription/users</td>
                                <td class="py-3 text-neutral-500">Create a new user</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">POST</span></td>
                                <td class="py-3 pr-4 font-mono text-xs">/api/v1/subscription/teams</td>
                                <td class="py-3 text-neutral-500">Create a new team (optionally assign owner)</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">POST</span></td>
                                <td class="py-3 pr-4 font-mono text-xs">/api/v1/subscription/teams/{team_id}/members</td>
                                <td class="py-3 text-neutral-500">Add or update a user's role in a team</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">POST</span></td>
                                <td class="py-3 pr-4 font-mono text-xs">/api/v1/subscription/plans</td>
                                <td class="py-3 text-neutral-500">Create a new plan (with all limit fields)</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">POST</span></td>
                                <td class="py-3 pr-4 font-mono text-xs">/api/v1/subscription/assign</td>
                                <td class="py-3 text-neutral-500">Assign a plan to a team (cancels existing active/trial first)</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">POST</span></td>
                                <td class="py-3 pr-4 font-mono text-xs">/api/v1/subscription/cancel</td>
                                <td class="py-3 text-neutral-500">Cancel a team's active subscription</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">POST</span></td>
                                <td class="py-3 pr-4 font-mono text-xs">/api/v1/subscription/payments</td>
                                <td class="py-3 text-neutral-500">Record a payment</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">GET</span></td>
                                <td class="py-3 pr-4 font-mono text-xs">/api/v1/subscription/teams</td>
                                <td class="py-3 text-neutral-500">List all teams with members &amp; subscription</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4"><span class="text-xs font-semibold px-2 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">GET</span></td>
                                <td class="py-3 pr-4 font-mono text-xs">/api/v1/subscription/teams/{team_id}/status</td>
                                <td class="py-3 text-neutral-500">Get team subscription &amp; usage stats</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Request Bodies --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="coolbox">
                        <h4 class="pb-3 font-semibold">POST /users</h4>
                        <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">{
  "name": "Jane Doe",         // required
  "email": "jane@example.com", // required
  "password": "secret",        // required
  "role": "admin"              // optional (default: member)
}</pre>
                    </div>
                    <div class="coolbox">
                        <h4 class="pb-3 font-semibold">POST /teams</h4>
                        <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">{
  "name": "Acme Corp",         // required
  "description": "...",        // optional
  "owner_id": 42               // optional — attach existing user as owner
}</pre>
                    </div>
                    <div class="coolbox">
                        <h4 class="pb-3 font-semibold">POST /teams/{team_id}/members</h4>
                        <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">{
  "user_id": 7,                // required
  "role": "member"             // optional (default: member)
}</pre>
                    </div>
                    <div class="coolbox">
                        <h4 class="pb-3 font-semibold">POST /plans</h4>
                        <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">{
  "name": "Pro",               // required
  "price": 29.00,              // optional
  "billing_cycle": "monthly",  // optional (free|monthly|yearly|lifetime)
  "features": ["Feature A"],   // optional
  "resources_limit": 50,       // optional (null = unlimited)
  "projects_limit": 10,        // optional
  "bandwidth_limit": 100,      // optional (GB)
  "storage_limit": 50,         // optional (GB)
  "team_members_limit": 5      // optional
}</pre>
                    </div>
                    <div class="coolbox">
                        <h4 class="pb-3 font-semibold">POST /assign</h4>
                        <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">{
  "team_id": 3,                // required
  "plan_id": 1,                // required
  "status": "active",          // optional (active|trial)
  "starts_at": "2026-04-01",   // optional
  "expires_at": "2027-04-01",  // optional
  "notes": "Promo deal"        // optional
}</pre>
                    </div>
                    <div class="coolbox">
                        <h4 class="pb-3 font-semibold">POST /cancel</h4>
                        <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">{
  "team_id": 3                 // required
}</pre>
                    </div>
                    <div class="coolbox">
                        <h4 class="pb-3 font-semibold">POST /payments</h4>
                        <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">{
  "team_id": 3,                // required
  "amount": 29.00,             // required
  "currency": "USD",           // optional (default: USD)
  "status": "paid",            // optional (paid|pending|failed|refunded)
  "description": "April 2026", // optional
  "paid_at": "2026-04-01",     // optional (ISO datetime)
  "team_plan_id": 5            // optional — link to plan assignment
}</pre>
                    </div>
                    <div class="coolbox">
                        <h4 class="pb-3 font-semibold">GET /teams</h4>
                        <p class="text-xs text-neutral-500 pb-3">No request body. Returns all teams with their members and active subscription data.</p>
                        <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">[
  {
    "id": 1,
    "name": "Acme Corp",
    "description": "...",
    "personal_team": false,
    "created_at": "2026-04-01T00:00:00Z",
    "members": [
      { "id": 7, "name": "Jane", "email": "jane@...", "role": "owner" }
    ],
    "subscription": {
      "id": 5, "status": "active",
      "plan": { "id": 1, "name": "Pro", ... }
    }
  }
]</pre>
                    </div>
                    <div class="coolbox">
                        <h4 class="pb-3 font-semibold">GET /teams/{team_id}/status</h4>
                        <p class="text-xs text-neutral-500 pb-3">No request body. Returns subscription details and current resource usage for the team.</p>
                        <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">{
  "team_id": 3,
  "team_name": "Acme Corp",
  "subscription": { ... },
  "plan": { ... },
  "usage": { ... }
}</pre>
                    </div>
                </div>

                {{-- Fetch Example --}}
                <div class="coolbox">
                    <h3 class="pb-3">Example Request</h3>
                    <pre class="text-xs bg-neutral-100 dark:bg-coolgray-300 rounded p-3 overflow-x-auto">fetch('{{ config('app.url') }}/api/v1/subscription/assign', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer &lt;your-api-token&gt;',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({
    team_id: 3,
    plan_id: 1,
    status: 'active',
  }),
})
  .then(res => res.json())
  .then(data => console.log(data));</pre>
                </div>
            </div>
        </div>

        {{-- Payment History Tab --}}
        <div x-show="tab === 'payments'">
            <div class="grid gap-6 lg:grid-cols-2">
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
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
