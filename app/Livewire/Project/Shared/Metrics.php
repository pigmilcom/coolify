<?php

namespace App\Livewire\Project\Shared;

use App\Enums\ApplicationDeploymentStatus;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use Illuminate\View\View;
use Livewire\Component;

class Metrics extends Component
{
    public $resource;

    public string $chartId = 'metrics';

    public int $interval = 5;

    public bool $poll = true;

    public bool $networkSupported = false;

    public int $totalDeployments = 0;

    public int $successfulDeployments30d = 0;

    public int $failedDeployments30d = 0;

    public ?string $lastDeployedAt = null;

    public ?string $lastDeploymentCommit = null;

    public int $envVarCount = 0;

    public int $volumeCount = 0;

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        if ($this->resource instanceof Application) {
            $this->totalDeployments = ApplicationDeploymentQueue::where('application_id', $this->resource->id)->count();

            $this->successfulDeployments30d = ApplicationDeploymentQueue::where('application_id', $this->resource->id)
                ->where('status', ApplicationDeploymentStatus::FINISHED)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            $this->failedDeployments30d = ApplicationDeploymentQueue::where('application_id', $this->resource->id)
                ->where('status', ApplicationDeploymentStatus::FAILED)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            $lastDeploy = $this->resource->get_last_successful_deployment();
            $this->lastDeployedAt = $lastDeploy?->created_at?->diffForHumans();
            $this->lastDeploymentCommit = $lastDeploy ? str($lastDeploy->commit)->limit(7, '') : null;
        }

        $this->envVarCount = $this->resource->environment_variables()->count();
        $this->volumeCount = $this->resource->persistentStorages()->count();
    }

    public function pollData(): void
    {
        if ($this->poll || $this->interval <= 10) {
            $this->loadData();
            if ($this->interval > 10) {
                $this->poll = false;
            }
        }
    }

    public function loadData(): void
    {
        try {
            $cpuMetrics = $this->resource->getCpuMetrics($this->interval);
            $memoryMetrics = $this->resource->getMemoryMetrics($this->interval);
            $this->dispatch("refreshChartData-{$this->chartId}-cpu", [
                'seriesData' => $cpuMetrics,
            ]);
            $this->dispatch("refreshChartData-{$this->chartId}-memory", [
                'seriesData' => $memoryMetrics,
            ]);
        } catch (\Throwable $e) {
            handleError($e, $this);

            return;
        }

        try {
            $networkMetrics = $this->resource->getNetworkMetrics($this->interval);
            if ($networkMetrics !== null) {
                $this->networkSupported = true;
                $this->dispatch("refreshChartData-{$this->chartId}-network", $networkMetrics);
            }
        } catch (\Throwable) {
            // Silently ignore — endpoint not supported by this Sentinel version.
        }
    }

    public function setInterval(): void
    {
        if ($this->interval <= 10) {
            $this->poll = true;
        }
        $this->loadData();
    }

    public function render(): View
    {
        return view('livewire.project.shared.metrics');
    }
}
