<?php

namespace App\Traits;

trait HasMetrics
{
    public function getCpuMetrics(int $mins = 5): ?array
    {
        return $this->getMetrics('cpu', $mins, 'percent');
    }

    public function getMemoryMetrics(int $mins = 5): ?array
    {
        $field = $this->isServerMetrics() ? 'usedPercent' : 'used';

        return $this->getMetrics('memory', $mins, $field);
    }

    public function getCpuMetricsForContainer(string $containerUuid, int $mins = 5): ?array
    {
        return $this->getMetrics('cpu', $mins, 'percent', $containerUuid);
    }

    public function getMemoryMetricsForContainer(string $containerUuid, int $mins = 5): ?array
    {
        return $this->getMetrics('memory', $mins, 'used', $containerUuid);
    }

    /**
     * @return array{rx: array<array{int, float}>, tx: array<array{int, float}>}|null
     */
    public function getNetworkMetricsForContainer(string $containerUuid, int $mins = 5): ?array
    {
        return $this->getNetworkMetrics($mins, $containerUuid);
    }

    /**
     * @return array{rx: array<array{int, float}>, tx: array<array{int, float}>}|null
     */
    public function getNetworkMetrics(int $mins = 5, ?string $containerUuid = null): ?array
    {
        if ($this->isServerMetrics()) {
            return null;
        }

        $server = $this->getMetricsServer();
        if (! $server->isMetricsEnabled()) {
            return null;
        }

        $from = now()->subMinutes($mins)->toIso8601ZuluString();
        $resolvedUuid = $containerUuid ?? $this->uuid;
        $endpoint = "http://localhost:8888/api/container/{$resolvedUuid}/network/history?from={$from}";

        $response = instant_remote_process(
            ["docker exec coolify-sentinel sh -c 'curl -s -H \"Authorization: Bearer {$server->settings->sentinel_token}\" {$endpoint}'"],
            $server,
            false
        );

        $decoded = json_decode($response, true);

        if (! is_array($decoded) || isset($decoded['error'])) {
            return null;
        }

        $raw = collect($decoded);

        if ($mins > 60 && $raw->count() > 1000) {
            $rxData = downsampleLTTB($raw->map(fn ($m) => [(int) $m['time'], (float) ($m['rxBytes'] ?? 0)])->toArray(), 1000);
            $txData = downsampleLTTB($raw->map(fn ($m) => [(int) $m['time'], (float) ($m['txBytes'] ?? 0)])->toArray(), 1000);
        } else {
            $rxData = $raw->map(fn ($m) => [(int) $m['time'], (float) ($m['rxBytes'] ?? 0)])->toArray();
            $txData = $raw->map(fn ($m) => [(int) $m['time'], (float) ($m['txBytes'] ?? 0)])->toArray();
        }

        return ['rx' => $rxData, 'tx' => $txData];
    }

    /**
     * @return array{rx: float, tx: float}|null Total bytes transferred since the start of the current month.
     *                                           Sums positive deltas to correctly handle container restarts.
     */
    public function getContainerBandwidthMonthBytes(?string $containerUuid = null): ?array
    {
        if ($this->isServerMetrics()) {
            return null;
        }

        $server = $this->getMetricsServer();
        if (! $server->isMetricsEnabled()) {
            return null;
        }

        $from = now()->startOfMonth()->toIso8601ZuluString();
        $resolvedUuid = $containerUuid ?? $this->uuid;
        $endpoint = "http://localhost:8888/api/container/{$resolvedUuid}/network/history?from={$from}";

        $response = instant_remote_process(
            ["docker exec coolify-sentinel sh -c 'curl -s -H \"Authorization: Bearer {$server->settings->sentinel_token}\" {$endpoint}'"],
            $server,
            false
        );

        $decoded = json_decode($response, true);

        if (! is_array($decoded) || isset($decoded['error'])) {
            return null;
        }

        $raw = collect($decoded);

        if ($raw->isEmpty()) {
            return null;
        }

        // Sum positive deltas so container restarts (which reset the counter) are handled correctly.
        $rxTotal = 0.0;
        $txTotal = 0.0;
        $prev = null;

        foreach ($raw as $point) {
            if ($prev !== null) {
                $rxDelta = (float) ($point['rxBytes'] ?? 0) - (float) ($prev['rxBytes'] ?? 0);
                $txDelta = (float) ($point['txBytes'] ?? 0) - (float) ($prev['txBytes'] ?? 0);
                if ($rxDelta > 0) {
                    $rxTotal += $rxDelta;
                }
                if ($txDelta > 0) {
                    $txTotal += $txDelta;
                }
            }
            $prev = $point;
        }

        return ['rx' => $rxTotal, 'tx' => $txTotal];
    }

    /**
     * Returns total storage bytes used by the container's writable layer plus any named volumes.
     */
    public function getContainerStorageBytes(?string $containerUuid = null): ?int
    {
        if ($this->isServerMetrics()) {
            return null;
        }

        $server = $this->getMetricsServer();
        $name = escapeshellarg($containerUuid ?? $this->uuid);

        $cmd = 'NAME=' . $name . '; '
            . 'SZ=$(docker inspect --size "$NAME" --format \'{{.SizeRw}}\' 2>/dev/null); '
            . 'SZ=$(echo "$SZ" | tr -cd \'0-9\'); SZ=${SZ:-0}; '
            . 'VOLS=$(docker inspect "$NAME" --format \'{{range .Mounts}}{{if eq .Type "volume"}}{{.Source}} {{end}}{{end}}\' 2>/dev/null); '
            . 'VT=0; for V in $VOLS; do [ -n "$V" ] && S=$(du -sb "$V" 2>/dev/null | cut -f1) && VT=$((VT+${S:-0})); done; '
            . 'echo $((SZ+VT))';

        $result = instant_remote_process([$cmd], $server, false);

        $bytes = (int) trim($result);

        return $bytes > 0 ? $bytes : null;
    }

    private function getMetrics(string $type, int $mins, string $valueField, ?string $containerUuid = null): ?array
    {
        $server = $this->getMetricsServer();
        if (! $server->isMetricsEnabled()) {
            return null;
        }

        $from = now()->subMinutes($mins)->toIso8601ZuluString();
        $endpoint = $this->getMetricsEndpoint($type, $from, $containerUuid);

        $response = instant_remote_process(
            ["docker exec coolify-sentinel sh -c 'curl -H \"Authorization: Bearer {$server->settings->sentinel_token}\" {$endpoint}'"],
            $server,
            false
        );

        if (str($response)->contains('error')) {
            $error = json_decode($response, true);
            $error = data_get($error, 'error', 'Something is not okay, are you okay?');
            if ($error === 'Unauthorized') {
                $error = 'Unauthorized, please check your metrics token or restart Sentinel to set a new token.';
            }
            throw new \Exception($error);
        }

        $metrics = collect(json_decode($response, true))->map(function ($metric) use ($valueField) {
            return [(int) $metric['time'], (float) ($metric[$valueField] ?? 0.0)];
        })->toArray();

        if ($mins > 60 && count($metrics) > 1000) {
            $metrics = downsampleLTTB($metrics, 1000);
        }

        return $metrics;
    }

    private function isServerMetrics(): bool
    {
        return $this instanceof \App\Models\Server;
    }

    private function getMetricsServer(): \App\Models\Server
    {
        return $this->isServerMetrics() ? $this : $this->destination->server;
    }

    private function getMetricsEndpoint(string $type, string $from, ?string $uuid = null): string
    {
        $base = 'http://localhost:8888/api';
        if ($this->isServerMetrics()) {
            return "{$base}/{$type}/history?from={$from}";
        }

        $resolvedUuid = $uuid ?? $this->uuid;

        return "{$base}/container/{$resolvedUuid}/{$type}/history?from={$from}";
    }
}
