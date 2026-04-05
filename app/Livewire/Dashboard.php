<?php

namespace App\Livewire;

use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use Illuminate\Support\Collection;
use Livewire\Component;

class Dashboard extends Component
{
    public Collection $projects;

    public Collection $servers;

    public Collection $privateKeys;

    public array $planUsage = [];

    public bool $isUsageLoading = false;

    public function mount(): void
    {
        $this->privateKeys = PrivateKey::ownedByCurrentTeamCached();
        $this->servers = Server::ownedByCurrentTeamCached();
        $this->projects = Project::ownedByCurrentTeam()->with('environments')->get();
        $this->planUsage = currentTeam()->planUsage();
    }

    public function loadUsageData(): void
    {
        $synced = $this->planUsage['usage_synced_at'] ?? null;

        if ($synced && $synced->diffInMinutes(now()) < 5) {
            return;
        }

        $this->isUsageLoading = true;

        try {
            $totalStorageGb = 0.0;
            $totalBandwidthGb = 0.0;

            foreach (currentTeam()->servers as $server) {
                if (! $server->isFunctional()) {
                    continue;
                }

                $totalStorageGb += $server->getStorageUsedGb();
                $totalBandwidthGb += $server->getNetworkBytesGb();
            }

            currentTeam()->update([
                'storage_usage_gb' => round($totalStorageGb, 2),
                'bandwidth_usage_gb' => round($totalBandwidthGb, 2),
                'usage_synced_at' => now(),
            ]);

            $this->planUsage = currentTeam()->fresh()->planUsage();
        } catch (\Throwable $e) {
            handleError($e, $this);
        } finally {
            $this->isUsageLoading = false;
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dashboard');
    }
}
