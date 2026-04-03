<div>
    <x-slot:title>
        Projects
    </x-slot>
    @php
        $projectsLimit = $planUsage['plan']?->projects_limit;
        $atProjectsLimit = $projectsLimit !== null && $planUsage['projects_count'] >= $projectsLimit;
    @endphp
    <div class="flex gap-2">
        <h1>Projects</h1>
        @can('createAnyResource')
            @if (! $atProjectsLimit)
                <x-modal-input buttonTitle="+ Add" title="New Project">
                    <livewire:project.add-empty />
                </x-modal-input>
            @endif
        @endcan
    </div>
    <div class="subtitle">All your projects are here.</div>
    @if ($atProjectsLimit)
        <div class="mb-4 p-4 bg-warning/10 border border-warning rounded-lg flex items-center gap-3">
            <svg class="size-4 text-warning shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm text-warning font-medium">
                You've reached your plan's project limit of {{ $projectsLimit }}.
                @can('canAccessPlan')
                    <a href="{{ route('plan.show') }}" {{ wireNavigate() }} class="underline hover:opacity-80">Upgrade your plan</a> to create more projects.
                @endcan
                @can('canAccessOwnerResources')
                    <a href="{{ route('subscriptions.index') }}" {{ wireNavigate() }} class="underline hover:opacity-80">Upgrade your plan</a> to create more projects.
                @endcan
            </p>
        </div>
    @endif
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2 -mt-1">
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
</div>
