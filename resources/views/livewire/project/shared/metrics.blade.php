<div>
    <div class="flex items-center gap-2">
        <h2>Metrics</h2>
    </div>
    <div class="pb-4">Usage stats and performance for this resource.</div>

    {{-- Always-visible stats cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pb-6">
        @if ($resource instanceof \App\Models\Application)
            <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-3">
                <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Total Deployments</div>
                <div class="text-2xl font-bold">{{ number_format($totalDeployments) }}</div>
            </div>
            <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-3">
                <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Successful (30d)</div>
                <div class="text-2xl font-bold text-green-500">{{ number_format($successfulDeployments30d) }}</div>
            </div>
            <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-3">
                <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Failed (30d)</div>
                <div class="text-2xl font-bold {{ $failedDeployments30d > 0 ? 'text-error' : '' }}">{{ number_format($failedDeployments30d) }}</div>
            </div>
            <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-3">
                <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Last Deployed</div>
                @if ($lastDeployedAt)
                    <div class="text-sm font-semibold">{{ $lastDeployedAt }}</div>
                    @if ($lastDeploymentCommit)
                        <div class="text-xs font-mono text-neutral-400 mt-0.5">{{ $lastDeploymentCommit }}</div>
                    @endif
                @else
                    <div class="text-sm text-neutral-400">Never</div>
                @endif
            </div>
        @endif
        <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-3">
            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Env Variables</div>
            <div class="text-2xl font-bold">{{ $envVarCount }}</div>
        </div>
        <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-3">
            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Persistent Volumes</div>
            <div class="text-2xl font-bold">{{ $volumeCount }}</div>
        </div>
        <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-3">
            <div class="text-xs text-neutral-500 dark:text-neutral-400 mb-1">Status</div>
            <div class="text-sm font-semibold">
                @if (str($resource->status)->contains('running'))
                    <span class="text-green-500">Running</span>
                @elseif (str($resource->status)->contains('exited'))
                    <span class="text-error">Stopped</span>
                @else
                    <span class="text-neutral-400">{{ str($resource->status)->before(':') ?: 'Unknown' }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Live charts (Sentinel required) --}}
    <div>
        @if ($resource->getMorphClass() === 'App\Models\Application' && $resource->build_pack === 'dockercompose')
            <div class="alert alert-warning">Metrics charts are not available for Docker Compose applications yet.</div>
        @elseif(!$resource->destination->server->isMetricsEnabled())
            <div class="alert alert-warning pb-1">Live metrics charts require Sentinel &amp; Metrics to be enabled on the server.</div>
            <div>Go to <a class="underline dark:text-white" href="{{ route('server.show', $resource->destination->server->uuid) }}/sentinel" {{ wireNavigate() }}>Server settings</a> to enable it.</div>
        @else
            @if (!str($resource->status)->contains('running'))
                <div class="alert alert-warning">Live charts are only available when the container is running.</div>
            @else
                <div class="flex items-end gap-3 pb-4">
                    <div class="w-48">
                        <x-forms.select label="Interval" wire:change="setInterval" id="interval">
                            <option value="5">5 minutes (live)</option>
                            <option value="10">10 minutes (live)</option>
                            <option value="30">30 minutes</option>
                            <option value="60">1 hour</option>
                            <option value="720">12 hours</option>
                            <option value="10080">1 week</option>
                            <option value="43200">30 days</option>
                        </x-forms.select>
                    </div>
                </div>

                <div @if ($poll) wire:poll.5000ms='pollData' @endif x-init="$wire.loadData()"
                    class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {{-- CPU --}}
                    <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-4">
                        <h4 class="pb-2">CPU Usage</h4>
                        <div wire:ignore id="{!! $chartId !!}-cpu"></div>
                    </div>

                    {{-- Memory --}}
                    <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-4">
                        <h4 class="pb-2">Memory Usage</h4>
                        <div wire:ignore id="{!! $chartId !!}-memory"></div>
                    </div>

                    {{-- Network I/O (shown only when Sentinel supports it) --}}
                    @if ($networkSupported)
                        <div class="rounded-md border border-neutral-200 dark:border-coolgray-400 bg-white dark:bg-coolgray-100 p-4 lg:col-span-2">
                            <h4 class="pb-2">Network I/O</h4>
                            <div wire:ignore id="{!! $chartId !!}-network"></div>
                        </div>
                    @endif
                </div>

                <script>
                    (function() {
                        checkTheme();

                        // ─── CPU ───────────────────────────────────────────────────────────
                        const optionsCpu = {
                            stroke: { curve: 'straight', width: 2 },
                            chart: {
                                height: '150px',
                                id: '{!! $chartId !!}-cpu',
                                type: 'area',
                                toolbar: { show: true, tools: { download: false, selection: false, zoom: true, zoomin: false, zoomout: false, pan: false, reset: true } },
                                animations: { enabled: true },
                            },
                            fill: { type: 'gradient' },
                            dataLabels: { enabled: false },
                            grid: { show: true, borderColor: '' },
                            colors: [cpuColor],
                            xaxis: { type: 'datetime' },
                            series: [{ name: 'CPU %', data: [] }],
                            noData: { text: 'Loading...', style: { color: textColor } },
                            legend: { show: false },
                            tooltip: {
                                enabled: true,
                                marker: { show: false },
                                custom: function({ series, seriesIndex, dataPointIndex, w }) {
                                    const v = series[seriesIndex][dataPointIndex];
                                    const ts = w.globals.seriesX[seriesIndex][dataPointIndex];
                                    return tooltipHtml('CPU', v + '%', ts);
                                }
                            },
                        };
                        const cpuChart = new ApexCharts(document.getElementById('{!! $chartId !!}-cpu'), optionsCpu);
                        cpuChart.render();
                        Livewire.on('refreshChartData-{!! $chartId !!}-cpu', (chartData) => {
                            checkTheme();
                            cpuChart.updateOptions({
                                series: [{ data: chartData[0].seriesData }],
                                colors: [cpuColor],
                                xaxis: { type: 'datetime', labels: { show: true, style: { colors: textColor } } },
                                yaxis: { show: true, labels: { show: true, style: { colors: textColor }, formatter: v => Math.round(v) + ' %' } },
                                noData: { text: 'Loading...', style: { color: textColor } },
                            });
                        });

                        // ─── Memory ───────────────────────────────────────────────────────
                        const optionsMemory = {
                            stroke: { curve: 'straight', width: 2 },
                            chart: {
                                height: '150px',
                                id: '{!! $chartId !!}-memory',
                                type: 'area',
                                toolbar: { show: true, tools: { download: false, selection: false, zoom: true, zoomin: false, zoomout: false, pan: false, reset: true } },
                                animations: { enabled: true },
                            },
                            fill: { type: 'gradient' },
                            dataLabels: { enabled: false },
                            grid: { show: true, borderColor: '' },
                            colors: [ramColor],
                            xaxis: { type: 'datetime', labels: { show: true, style: { colors: textColor } } },
                            series: [{ name: 'Memory (MB)', data: [] }],
                            noData: { text: 'Loading...', style: { color: textColor } },
                            legend: { show: false },
                            tooltip: {
                                enabled: true,
                                marker: { show: false },
                                custom: function({ series, seriesIndex, dataPointIndex, w }) {
                                    const v = series[seriesIndex][dataPointIndex];
                                    const ts = w.globals.seriesX[seriesIndex][dataPointIndex];
                                    return tooltipHtml('Memory', v + ' MB', ts);
                                }
                            },
                        };
                        const memoryChart = new ApexCharts(document.getElementById('{!! $chartId !!}-memory'), optionsMemory);
                        memoryChart.render();
                        Livewire.on('refreshChartData-{!! $chartId !!}-memory', (chartData) => {
                            checkTheme();
                            memoryChart.updateOptions({
                                series: [{ data: chartData[0].seriesData }],
                                colors: [ramColor],
                                xaxis: { type: 'datetime', labels: { show: true, style: { colors: textColor } } },
                                yaxis: { min: 0, show: true, labels: { show: true, style: { colors: textColor }, formatter: v => Math.round(v) + ' MB' } },
                                noData: { text: 'Loading...', style: { color: textColor } },
                            });
                        });

                        // ─── Network I/O ──────────────────────────────────────────────────
                        @if ($networkSupported)
                        const optionsNetwork = {
                            stroke: { curve: 'straight', width: 2 },
                            chart: {
                                height: '150px',
                                id: '{!! $chartId !!}-network',
                                type: 'area',
                                toolbar: { show: true, tools: { download: false, selection: false, zoom: true, zoomin: false, zoomout: false, pan: false, reset: true } },
                                animations: { enabled: true },
                            },
                            fill: { type: 'gradient', opacity: [0.35, 0.15] },
                            dataLabels: { enabled: false },
                            grid: { show: true, borderColor: '' },
                            xaxis: { type: 'datetime', labels: { show: true, style: { colors: textColor } } },
                            series: [
                                { name: 'Received (RX)', data: [] },
                                { name: 'Sent (TX)', data: [] },
                            ],
                            colors: [ramColor, cpuColor],
                            noData: { text: 'Loading...', style: { color: textColor } },
                            legend: { show: true, labels: { colors: textColor } },
                            tooltip: {
                                enabled: true,
                                marker: { show: true },
                                custom: function({ series, seriesIndex, dataPointIndex, w }) {
                                    const v = series[seriesIndex][dataPointIndex];
                                    const ts = w.globals.seriesX[seriesIndex][dataPointIndex];
                                    const label = seriesIndex === 0 ? 'RX' : 'TX';
                                    return tooltipHtml(label, formatBytes(v), ts);
                                }
                            },
                        };
                        const networkChart = new ApexCharts(document.getElementById('{!! $chartId !!}-network'), optionsNetwork);
                        networkChart.render();
                        Livewire.on('refreshChartData-{!! $chartId !!}-network', (data) => {
                            checkTheme();
                            networkChart.updateOptions({
                                series: [
                                    { name: 'Received (RX)', data: data[0].rx },
                                    { name: 'Sent (TX)', data: data[0].tx },
                                ],
                                colors: [ramColor, cpuColor],
                                xaxis: { type: 'datetime', labels: { show: true, style: { colors: textColor } } },
                                yaxis: { show: true, labels: { show: true, style: { colors: textColor }, formatter: v => formatBytes(v) } },
                                noData: { text: 'Loading...', style: { color: textColor } },
                            });
                        });
                        @endif

                        function tooltipHtml(label, value, timestamp) {
                            const d = new Date(timestamp);
                            const time = String(d.getUTCHours()).padStart(2,'0') + ':' +
                                String(d.getUTCMinutes()).padStart(2,'0') + ':' +
                                String(d.getUTCSeconds()).padStart(2,'0') + ', ' +
                                d.getUTCFullYear() + '-' +
                                String(d.getUTCMonth()+1).padStart(2,'0') + '-' +
                                String(d.getUTCDate()).padStart(2,'0');
                            return '<div class="apexcharts-tooltip-custom">' +
                                '<div class="apexcharts-tooltip-custom-value">' + label + ': <span class="apexcharts-tooltip-value-bold">' + value + '</span></div>' +
                                '<div class="apexcharts-tooltip-custom-title">' + time + '</div>' +
                                '</div>';
                        }

                        function formatBytes(bytes) {
                            if (bytes === 0) return '0 B';
                            const k = 1024;
                            const sizes = ['B', 'KB', 'MB', 'GB'];
                            const i = Math.floor(Math.log(Math.abs(bytes)) / Math.log(k));
                            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                        }
                    })();
                </script>
            @endif
        @endif
    </div>
</div>

                <h4>CPU Usage</h4>
                <div wire:ignore id="{!! $chartId !!}-cpu"></div>

                <script>
                    (function() {
                        checkTheme();
                        const optionsServerCpu = {
                            stroke: {
                                curve: 'straight',
                                width: 2,
                            },
                            chart: {
                                height: '150px',
                                id: '{!! $chartId !!}-cpu',
                                type: 'area',
                                toolbar: {
                                    show: true,
                                    tools: {
                                        download: false,
                                        selection: false,
                                        zoom: true,
                                        zoomin: false,
                                        zoomout: false,
                                        pan: false,
                                        reset: true
                                    },
                                },
                                animations: {
                                    enabled: true,
                                },
                            },
                            fill: {
                                type: 'gradient',
                            },
                            dataLabels: {
                                enabled: false,
                                offsetY: -10,
                                style: {
                                    colors: ['#FCD452'],
                                },
                                background: {
                                    enabled: false,
                                }
                            },
                             grid: {
                                 show: true,
                                 borderColor: '',
                             },
                             colors: [cpuColor],
                             xaxis: {
                                 type: 'datetime',
                             },
                              series: [{
                                  name: "CPU %",
                                 data: []
                             }],
                             noData: {
                                 text: 'Loading...',
                                 style: {
                                     color: textColor,
                                 }
                             },
                             tooltip: {
                                 enabled: true,
                                 marker: {
                                     show: false,
                                 },
                                 custom: function({ series, seriesIndex, dataPointIndex, w }) {
                                     const value = series[seriesIndex][dataPointIndex];
                                     const timestamp = w.globals.seriesX[seriesIndex][dataPointIndex];
                                     const date = new Date(timestamp);
                                     const timeString = String(date.getUTCHours()).padStart(2, '0') + ':' +
                                         String(date.getUTCMinutes()).padStart(2, '0') + ':' +
                                         String(date.getUTCSeconds()).padStart(2, '0') + ', ' +
                                         date.getUTCFullYear() + '-' +
                                         String(date.getUTCMonth() + 1).padStart(2, '0') + '-' +
                                         String(date.getUTCDate()).padStart(2, '0');
                                     return '<div class="apexcharts-tooltip-custom">' +
                                         '<div class="apexcharts-tooltip-custom-value">CPU: <span class="apexcharts-tooltip-value-bold">' + value + '%</span></div>' +
                                         '<div class="apexcharts-tooltip-custom-title">' + timeString + '</div>' +
                                         '</div>';
                                 }
                             },
                             legend: {
                                 show: false
                             }
                        }
                         const serverCpuChart = new ApexCharts(document.getElementById(`{!! $chartId !!}-cpu`), optionsServerCpu);
                         serverCpuChart.render();
                         Livewire.on('refreshChartData-{!! $chartId !!}-cpu', (chartData) => {
                             checkTheme();
                              serverCpuChart.updateOptions({
                                  series: [{
                                      data: chartData[0].seriesData,
                                  }],
                                  colors: [cpuColor],
                                 xaxis: {
                                     type: 'datetime',
                                     labels: {
                                         show: true,
                                         style: {
                                             colors: textColor,
                                         }
                                     }
                                 },
                                  yaxis: {
                                      show: true,
                                      labels: {
                                          show: true,
                                          style: {
                                              colors: textColor,
                                          },
                                          formatter: function(value) {
                                              return Math.round(value) + ' %';
                                          }
                                      }
                                  },
                                 noData: {
                                     text: 'Loading...',
                                     style: {
                                         color: textColor,
                                     }
                                 }
                             });
                         });
                    })();
                </script>

                <h4>Memory Usage</h4>
                <div wire:ignore id="{!! $chartId !!}-memory"></div>

                <script>
                    (function() {
                        checkTheme();
                        const optionsServerMemory = {
                            stroke: {
                                curve: 'straight',
                                width: 2,
                            },
                            chart: {
                                height: '150px',
                                id: '{!! $chartId !!}-memory',
                                type: 'area',
                                toolbar: {
                                    show: true,
                                    tools: {
                                        download: false,
                                        selection: false,
                                        zoom: true,
                                        zoomin: false,
                                        zoomout: false,
                                        pan: false,
                                        reset: true
                                    },
                                },
                                animations: {
                                    enabled: true,
                                },
                            },
                            fill: {
                                type: 'gradient',
                            },
                            dataLabels: {
                                enabled: false,
                                offsetY: -10,
                                style: {
                                    colors: ['#FCD452'],
                                },
                                background: {
                                    enabled: false,
                                }
                            },
                             grid: {
                                 show: true,
                                 borderColor: '',
                             },
                             colors: [ramColor],
                             xaxis: {
                                 type: 'datetime',
                                 labels: {
                                     show: true,
                                     style: {
                                         colors: textColor,
                                     }
                                 }
                             },
                             series: [{
                                 name: "Memory (MB)",
                                 data: []
                             }],
                             noData: {
                                 text: 'Loading...',
                                 style: {
                                     color: textColor,
                                 }
                             },
                             tooltip: {
                                 enabled: true,
                                 marker: {
                                     show: false,
                                 },
                                 custom: function({ series, seriesIndex, dataPointIndex, w }) {
                                     const value = series[seriesIndex][dataPointIndex];
                                     const timestamp = w.globals.seriesX[seriesIndex][dataPointIndex];
                                     const date = new Date(timestamp);
                                     const timeString = String(date.getUTCHours()).padStart(2, '0') + ':' +
                                         String(date.getUTCMinutes()).padStart(2, '0') + ':' +
                                         String(date.getUTCSeconds()).padStart(2, '0') + ', ' +
                                         date.getUTCFullYear() + '-' +
                                         String(date.getUTCMonth() + 1).padStart(2, '0') + '-' +
                                         String(date.getUTCDate()).padStart(2, '0');
                                     return '<div class="apexcharts-tooltip-custom">' +
                                         '<div class="apexcharts-tooltip-custom-value">Memory: <span class="apexcharts-tooltip-value-bold">' + value + ' MB</span></div>' +
                                         '<div class="apexcharts-tooltip-custom-title">' + timeString + '</div>' +
                                         '</div>';
                                 }
                             },
                             legend: {
                                 show: false
                             }
                        }
                         const serverMemoryChart = new ApexCharts(document.getElementById(`{!! $chartId !!}-memory`),
                             optionsServerMemory);
                         serverMemoryChart.render();
                         Livewire.on('refreshChartData-{!! $chartId !!}-memory', (chartData) => {
                             checkTheme();
                              serverMemoryChart.updateOptions({
                                  series: [{
                                      data: chartData[0].seriesData,
                                  }],
                                  colors: [ramColor],
                                 xaxis: {
                                     type: 'datetime',
                                     labels: {
                                         show: true,
                                         style: {
                                             colors: textColor,
                                         }
                                     }
                                 },
                                  yaxis: {
                                      min: 0,
                                      show: true,
                                      labels: {
                                          show: true,
                                          style: {
                                              colors: textColor,
                                          },
                                          formatter: function(value) {
                                              return Math.round(value) + ' MB';
                                          }
                                      }
                                  },
                                 noData: {
                                     text: 'Loading...',
                                     style: {
                                         color: textColor,
                                     }
                                 }
                             });
                         });
                    })();
                </script>
            </div>
            </div>
        @endif
    @endif
    </div>
</div>
