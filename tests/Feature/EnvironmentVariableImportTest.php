<?php

use App\Livewire\Project\Shared\EnvironmentVariable\All;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    $this->actingAs($this->user);
    session(['currentTeam' => $this->team]);

    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
    $this->application = Application::factory()->create(['environment_id' => $this->environment->id]);
});

describe('Environment Variable Import (.env file)', function () {
    test('imports new environment variables from .env file content', function () {
        $content = "APP_NAME=Coolify\nAPP_ENV=production\nAPP_DEBUG=false";

        Livewire::test(All::class, ['resource' => $this->application])
            ->assertSuccessful()
            ->call('importEnvFile', $content);

        expect($this->application->environment_variables()->where('key', 'APP_NAME')->value('value'))->toBe('Coolify');
        expect($this->application->environment_variables()->where('key', 'APP_ENV')->value('value'))->toBe('production');
        expect($this->application->environment_variables()->where('key', 'APP_DEBUG')->value('value'))->toBe('false');
    });

    test('overwrites existing environment variable values on import', function () {
        EnvironmentVariable::create([
            'key' => 'APP_ENV',
            'value' => 'local',
            'is_preview' => false,
            'resourceable_id' => $this->application->id,
            'resourceable_type' => $this->application->getMorphClass(),
        ]);

        $content = "APP_ENV=production\nNEW_VAR=hello";

        Livewire::test(All::class, ['resource' => $this->application])
            ->assertSuccessful()
            ->call('importEnvFile', $content);

        expect($this->application->environment_variables()->where('key', 'APP_ENV')->value('value'))->toBe('production');
        expect($this->application->environment_variables()->where('key', 'NEW_VAR')->value('value'))->toBe('hello');
    });

    test('dispatches error when file content is empty', function () {
        Livewire::test(All::class, ['resource' => $this->application])
            ->assertSuccessful()
            ->call('importEnvFile', '   ')
            ->assertDispatched('error');

        expect($this->application->environment_variables()->count())->toBe(0);
    });

    test('dispatches error when .env file content contains no valid variables', function () {
        $content = "# just a comment\n\n# another comment";

        Livewire::test(All::class, ['resource' => $this->application])
            ->assertSuccessful()
            ->call('importEnvFile', $content)
            ->assertDispatched('error');
    });

    test('ignores comment lines and blank lines when importing', function () {
        $content = "# This is a comment\nVALID_KEY=valid_value\n\n# Another comment\nSECOND_KEY=second_value";

        Livewire::test(All::class, ['resource' => $this->application])
            ->assertSuccessful()
            ->call('importEnvFile', $content);

        expect($this->application->environment_variables()->count())->toBe(2);
        expect($this->application->environment_variables()->where('key', 'VALID_KEY')->exists())->toBeTrue();
        expect($this->application->environment_variables()->where('key', 'SECOND_KEY')->exists())->toBeTrue();
    });

    test('strips surrounding quotes from values when importing', function () {
        $content = "QUOTED_DOUBLE=\"hello world\"\nQUOTED_SINGLE='foo bar'";

        Livewire::test(All::class, ['resource' => $this->application])
            ->assertSuccessful()
            ->call('importEnvFile', $content);

        expect($this->application->environment_variables()->where('key', 'QUOTED_DOUBLE')->value('value'))->toBe('hello world');
        expect($this->application->environment_variables()->where('key', 'QUOTED_SINGLE')->value('value'))->toBe('foo bar');
    });

    test('skips variables already defined in docker compose environment section', function () {
        $dockerCompose = "services:\n  app:\n    environment:\n      DB_HOST: localhost\n      DB_PORT: \"5432\"";
        $this->application->update(['build_pack' => 'dockercompose', 'docker_compose' => $dockerCompose]);

        $content = "DB_HOST=myhost\nDB_PORT=3306\nAPP_KEY=secret";

        Livewire::test(All::class, ['resource' => $this->application])
            ->assertSuccessful()
            ->call('importEnvFile', $content)
            ->assertDispatched('success');

        expect($this->application->environment_variables()->where('key', 'DB_HOST')->exists())->toBeFalse();
        expect($this->application->environment_variables()->where('key', 'DB_PORT')->exists())->toBeFalse();
        expect($this->application->environment_variables()->where('key', 'APP_KEY')->value('value'))->toBe('secret');
    });

    test('skips variables already defined via ENV in dockerfile', function () {
        $dockerfile = "FROM php:8.4\nENV APP_ENV=production\nENV NODE_ENV=production APP_PORT=8080\nRUN echo done";
        $this->application->update(['build_pack' => 'dockerfile', 'dockerfile' => $dockerfile]);

        $content = "APP_ENV=local\nNODE_ENV=development\nAPP_PORT=3000\nAPP_KEY=newkey";

        Livewire::test(All::class, ['resource' => $this->application])
            ->assertSuccessful()
            ->call('importEnvFile', $content)
            ->assertDispatched('success');

        expect($this->application->environment_variables()->where('key', 'APP_ENV')->exists())->toBeFalse();
        expect($this->application->environment_variables()->where('key', 'NODE_ENV')->exists())->toBeFalse();
        expect($this->application->environment_variables()->where('key', 'APP_PORT')->exists())->toBeFalse();
        expect($this->application->environment_variables()->where('key', 'APP_KEY')->value('value'))->toBe('newkey');
    });

    test('dispatches error when all variables are already defined in docker compose', function () {
        $dockerCompose = "services:\n  app:\n    environment:\n      APP_KEY: abc123";
        $this->application->update(['build_pack' => 'dockercompose', 'docker_compose' => $dockerCompose]);

        $content = "APP_KEY=newvalue";

        Livewire::test(All::class, ['resource' => $this->application])
            ->assertSuccessful()
            ->call('importEnvFile', $content)
            ->assertDispatched('error');

        expect($this->application->environment_variables()->count())->toBe(0);
    });
});
