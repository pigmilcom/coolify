<div>
    <x-slot:title>
        Dashboard | Console
    </x-slot>
    @if (session('error'))
        <span x-data x-init="$wire.emit('error', '{{ session('error') }}')" />
    @endif
    <h1>Dashboard</h1>
    <div class="subtitle">Your self-hosted infrastructure.</div>
    @if (request()->query->get('success'))
        <div class=" mb-10 font-bold alert alert-success">
            Your subscription has been activated! Welcome onboard! It could take a few seconds before your
            subscription is activated.<br> Please be patient.
        </div>
    @endif

    {{-- Plan Stats --}}
    <section class="mb-6">
        @php
            $plan = $planUsage['plan'];
            $teamPlan = $planUsage['team_plan'];
            $projectsCount = $planUsage['projects_count'];
            $resourcesCount = $planUsage['resources_count'];
            $membersCount = $planUsage['team_members_count'];
            $usageStats = [
                ['label' => 'Projects', 'current' => $projectsCount, 'limit' => $plan?->projects_limit, 'unit' => ''],
                ['label' => 'Resources', 'current' => $resourcesCount, 'limit' => $plan?->resources_limit, 'unit' => ''],
                ['label' => 'Team Members', 'current' => $membersCount, 'limit' => $plan?->team_members_limit, 'unit' => ''],
                ['label' => 'Bandwidth', 'current' => $planUsage['bandwidth_usage_gb'], 'limit' => $plan?->bandwidth_limit, 'unit' => 'GB'],
                ['label' => 'Storage', 'current' => $planUsage['storage_usage_gb'], 'limit' => $plan?->storage_limit, 'unit' => 'GB'],
            ];
        @endphp
        <div class="flex items-center gap-2 pb-2">
            <h3>Plan</h3>
            @can('canAccessOwnerResources')
                <a href="{{ route('subscriptions.index') }}" {{ wireNavigate() }} class="text-xs text-coollabs hover:underline">Manage</a>
            @endcan
            @can('canAccessPlan')
                <a href="{{ route('plan.show') }}" {{ wireNavigate() }} class="text-xs text-coollabs hover:underline">View Plan</a>
            @endcan
        </div>
        <div class="coolbox">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                <div>
                    <div class="text-lg font-bold">{{ $plan?->name ?? 'Free / Trial' }}</div>
                    @if ($teamPlan)
                        <div class="flex items-center gap-2 mt-1">
                            <span @class([
                                'text-xs px-2 py-0.5 rounded font-medium',
                                'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $teamPlan->status === 'active',
                                'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' => $teamPlan->status === 'trial',
                            ])>{{ ucfirst($teamPlan->status) }}</span>
                            @if ($teamPlan->expires_at)
                                <span class="text-xs text-neutral-500">Expires {{ $teamPlan->expires_at->format('M d, Y') }}</span>
                            @endif
                        </div>
                    @else
                        <span class="text-xs text-neutral-500">No active plan assigned.</span>
                    @endif
                </div>
                @can('canAccessPlan')
                    <a href="{{ route('plan.show') }}" {{ wireNavigate() }} class="button shrink-0">View Plans</a>
                @endcan
                @can('canAccessOwnerResources')
                    <a href="{{ route('subscriptions.index') }}" {{ wireNavigate() }} class="button shrink-0">Manage Plans</a>
                @endcan
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($usageStats as $stat)
                    @php
                        $unlimited = $stat['limit'] === null;
                        $pct = $unlimited ? 0 : ($stat['limit'] > 0 ? min(100, (int) round($stat['current'] / $stat['limit'] * 100)) : 100);
                        $isAtLimit = ! $unlimited && $stat['current'] >= $stat['limit'];
                        $isNearLimit = ! $unlimited && ! $isAtLimit && $pct >= 80;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium">{{ $stat['label'] }}</span>
                            <span class="text-xs text-neutral-500">
                                {{ $stat['current'] }}{{ $stat['unit'] ? ' '.$stat['unit'] : '' }}
                                /
                                {{ $unlimited ? 'Unlimited' : $stat['limit'].($stat['unit'] ? ' '.$stat['unit'] : '') }}
                            </span>
                        </div>
                        <div class="w-full bg-neutral-200 dark:bg-coolgray-200 rounded-full h-1.5">
                            <div @class([
                                'h-1.5 rounded-full transition-all',
                                'bg-coollabs' => ! $isAtLimit && ! $isNearLimit,
                                'bg-warning' => $isNearLimit,
                                'bg-error' => $isAtLimit,
                            ]) style="width: {{ $unlimited ? '0' : $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($planUsage['usage_synced_at'])
                <p class="text-xs text-neutral-400 mt-2">Storage last synced {{ $planUsage['usage_synced_at']->diffForHumans() }}.</p>
            @else
                <p class="text-xs text-neutral-400 mt-2">Storage usage syncs daily. Run <code class="font-mono">php artisan schedule:run</code> to update now.</p>
            @endif
        </div>
    </section>

    <section class="-mt-2">
        <div class="flex items-center gap-2 pb-2">
            <h3>Projects</h3>
            @if ($projects->count() > 0)
                <x-modal-input buttonTitle="Add" title="New Project">
                    <x-slot:content>
                        <button
                            class="flex items-center justify-center size-4 text-white rounded hover:bg-coolgray-400 dark:hover:bg-coolgray-300 cursor-pointer">
                            <svg class="size-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </x-slot:content>
                    <livewire:project.add-empty />
                </x-modal-input>
            @endif
        </div>
        @if ($projects->count() > 0)
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach ($projects as $project)
                    <div class="relative gap-2 cursor-pointer coolbox group">
                        <a href="{{ $project->navigateTo() }}" {{ wireNavigate() }} class="absolute inset-0"></a>
                        <div class="flex flex-1 mx-6">
                            <div class="flex flex-col justify-center flex-1">
                                <div class="box-title">{{ $project->name }}</div>
                                <div class="box-description">
                                    {{ $project->description }}
                                </div>
                            </div>
                            <div class="relative z-10 flex items-center justify-center gap-4 text-xs font-bold">
                                @if ($project->environments->first())
                                    @can('createAnyResource')
                                        <a class="hover:underline" {{ wireNavigate() }}
                                            href="{{ route('project.resource.create', [
                                                'project_uuid' => $project->uuid,
                                                'environment_uuid' => $project->environments->first()->uuid,
                                            ]) }}">
                                            + Add Resource
                                        </a>
                                    @endcan
                                @endif
                                @can('update', $project)
                                    <a class="hover:underline" {{ wireNavigate() }}
                                        href="{{ route('project.edit', ['project_uuid' => $project->uuid]) }}">
                                        Settings
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col gap-1">
                <div class='font-bold dark:text-warning'>No projects found.</div>
                <div class="flex items-center gap-1">
                    <x-modal-input buttonTitle="Add" title="New Project">
                        <livewire:project.add-empty />
                    </x-modal-input> your first project or
                    go to the <a class="underline dark:text-white" href="{{ route('onboarding') }}" {{ wireNavigate() }}>onboarding</a> page.
                </div>
            </div>
        @endif
    </section>

</div>
