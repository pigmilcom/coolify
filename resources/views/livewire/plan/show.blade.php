<div>
    <x-slot:title>
        Plan | Coolify
    </x-slot>

    <div class="flex items-start gap-2 pb-6">
        <div>
            <h1 class="pb-2">Your Plan</h1>
            <div class="subtitle">View your current plan and available subscription options.</div>
        </div>
    </div>

    {{-- Current Plan --}}
    <div class="flex flex-col gap-6">
        <div>
            <h3 class="pb-3">Current Plan</h3>
            @if ($currentTeamPlan)
                <div class="coolbox max-w-lg">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex flex-col gap-2">
                            <div class="text-xl font-bold">{{ $currentTeamPlan->plan->name }}</div>
                            @if ($currentTeamPlan->plan->description)
                                <p class="text-sm text-neutral-500">{{ $currentTeamPlan->plan->description }}</p>
                            @endif
                            <div class="text-lg font-semibold text-coollabs">{{ $currentTeamPlan->plan->formattedPrice() }}</div>
                            <div class="flex items-center gap-2">
                                <span @class([
                                    'text-xs px-2 py-1 rounded font-medium',
                                    'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $currentTeamPlan->status === 'active',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' => $currentTeamPlan->status === 'trial',
                                ])>{{ ucfirst($currentTeamPlan->status) }}</span>
                                @if ($currentTeamPlan->expires_at)
                                    <span class="text-sm text-neutral-500">Expires {{ $currentTeamPlan->expires_at->format('M d, Y') }}</span>
                                @endif
                            </div>
                            @if ($currentTeamPlan->plan->features)
                                <ul class="mt-2 space-y-1">
                                    @foreach ($currentTeamPlan->plan->features as $feature)
                                        <li class="flex items-center gap-2 text-sm">
                                            <svg class="w-4 h-4 text-green-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M20 6L9 17l-5-5" />
                                            </svg>
                                            {{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($currentTeamPlan->starts_at)
                                <p class="text-xs text-neutral-500 mt-1">Active since {{ $currentTeamPlan->starts_at->format('M d, Y') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="coolbox max-w-lg">
                    <div class="flex flex-col gap-2">
                        <div class="text-xl font-bold">Free / Trial</div>
                        <p class="text-sm text-neutral-500">You are currently on the free/trial plan. Contact the administrator to upgrade.</p>
                        <span class="text-xs px-2 py-1 rounded font-medium bg-neutral-200 text-neutral-600 dark:bg-coolgray-200 dark:text-neutral-400 w-fit">Free</span>
                    </div>
                </div>
            @endif
        </div>

        {{-- Available Plans --}}
        @if ($availablePlans->isNotEmpty())
            <div>
                <h3 class="pb-3">Available Plans</h3>
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach ($availablePlans as $plan)
                        <div @class([
                            'coolbox flex flex-col gap-2',
                            'ring-2 ring-coollabs' => $currentTeamPlan && $currentTeamPlan->plan_id === $plan->id,
                        ])>
                            <div class="flex items-center justify-between">
                                <div class="font-bold text-lg">{{ $plan->name }}</div>
                                @if ($currentTeamPlan && $currentTeamPlan->plan_id === $plan->id)
                                    <span class="text-xs px-2 py-0.5 rounded bg-coollabs text-white">Current</span>
                                @endif
                            </div>
                            @if ($plan->description)
                                <p class="text-sm text-neutral-500">{{ $plan->description }}</p>
                            @endif
                            <div class="text-lg font-semibold text-coollabs">{{ $plan->formattedPrice() }}</div>
                            @if ($plan->features)
                                <ul class="mt-1 space-y-1">
                                    @foreach ($plan->features as $feature)
                                        <li class="flex items-center gap-2 text-sm">
                                            <svg class="w-4 h-4 text-green-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M20 6L9 17l-5-5" />
                                            </svg>
                                            {{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if (! ($currentTeamPlan && $currentTeamPlan->plan_id === $plan->id))
                                <p class="text-xs text-neutral-500 mt-auto pt-2">Contact the administrator to switch to this plan.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="coolbox max-w-lg text-neutral-500">No plans are currently available. Contact the administrator for more information.</div>
        @endif
    </div>
</div>
