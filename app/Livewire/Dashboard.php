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

    public function mount()
    {
        $this->privateKeys = PrivateKey::ownedByCurrentTeamCached();
        $this->servers = Server::ownedByCurrentTeamCached();
        $this->projects = Project::ownedByCurrentTeam()->with('environments')->get();
        $this->planUsage = currentTeam()->planUsage();
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
