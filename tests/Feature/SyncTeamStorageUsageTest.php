<?php

use App\Jobs\SyncTeamStorageUsageJob;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('updates team storage_usage_gb after sync', function () {
    Queue::fake();

    $team = Team::factory()->create([
        'storage_usage_gb' => 0,
        'usage_synced_at' => null,
    ]);

    $team->update([
        'storage_usage_gb' => 12.5,
        'usage_synced_at' => now(),
    ]);

    $team->refresh();

    expect($team->storage_usage_gb)->toBe(12.5)
        ->and($team->usage_synced_at)->not->toBeNull();
});

it('dispatches SyncTeamStorageUsageJob to the queue', function () {
    Queue::fake();

    $team = Team::factory()->create();

    SyncTeamStorageUsageJob::dispatch($team);

    Queue::assertPushed(SyncTeamStorageUsageJob::class, function ($job) use ($team) {
        return $job->team->id === $team->id;
    });
});

it('team planUsage returns storage and bandwidth usage fields', function () {
    $team = Team::factory()->create([
        'storage_usage_gb' => 5.75,
        'bandwidth_usage_gb' => 0,
        'usage_synced_at' => now(),
    ]);

    $usage = $team->planUsage();

    expect($usage)->toHaveKeys(['storage_usage_gb', 'bandwidth_usage_gb', 'usage_synced_at'])
        ->and($usage['storage_usage_gb'])->toBe(5.75)
        ->and($usage['bandwidth_usage_gb'])->toBe(0.0);
});

it('team planUsage defaults storage usage to zero when not synced', function () {
    $team = Team::factory()->create([
        'storage_usage_gb' => 0,
        'bandwidth_usage_gb' => 0,
        'usage_synced_at' => null,
    ]);

    $usage = $team->planUsage();

    expect($usage['storage_usage_gb'])->toBe(0.0)
        ->and($usage['usage_synced_at'])->toBeNull();
});
