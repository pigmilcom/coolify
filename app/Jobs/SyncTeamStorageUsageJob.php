<?php

namespace App\Jobs;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\Horizon\Contracts\Silenced;

class SyncTeamStorageUsageJob implements ShouldBeEncrypted, ShouldQueue, Silenced
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public Team $team) {}

    public function backoff(): int
    {
        return isDev() ? 1 : 3;
    }

    public function handle(): void
    {
        try {
            $totalGb = 0.0;

            foreach ($this->team->servers as $server) {
                if (! $server->isFunctional()) {
                    continue;
                }

                $totalGb += $server->getStorageUsedGb();
            }

            $this->team->update([
                'storage_usage_gb' => round($totalGb, 2),
                'usage_synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            handleError($e);
        }
    }
}
