<?php

namespace App\Traits;

use Symfony\Component\Yaml\Yaml;

trait EnvironmentVariableProtection
{
    /**
     * Check if an environment variable is protected from deletion
     *
     * @param  string  $key  The environment variable key to check
     * @return bool True if the variable is protected, false otherwise
     */
    protected function isProtectedEnvironmentVariable(string $key): bool
    {
        return str($key)->startsWith('SERVICE_FQDN_') || str($key)->startsWith('SERVICE_URL_') || str($key)->startsWith('SERVICE_NAME_');
    }

    /**
     * Check if an environment variable is used in Docker Compose
     *
     * @param  string  $key  The environment variable key to check
     * @param  string|null  $dockerCompose  The Docker Compose YAML content
     * @return array [bool $isUsed, string $reason] Whether the variable is used and the reason if it is
     */
    protected function isEnvironmentVariableUsedInDockerCompose(string $key, ?string $dockerCompose): array
    {
        if (empty($dockerCompose)) {
            return [false, ''];
        }

        try {
            $dockerComposeData = Yaml::parse($dockerCompose);
            $dockerEnvVars = data_get($dockerComposeData, 'services.*.environment');

            foreach ($dockerEnvVars as $serviceEnvs) {
                if (! is_array($serviceEnvs)) {
                    continue;
                }

                // Check for direct variable usage
                foreach ($serviceEnvs as $env => $value) {
                    if ($env === $key) {
                        return [true, "Environment variable '{$key}' is used directly in the Docker Compose file."];
                    }
                }

                // Check for variable references in values
                foreach ($serviceEnvs as $env => $value) {
                    if (is_string($value) && str_contains($value, '$'.$key)) {
                        return [true, "Environment variable '{$key}' is referenced in the Docker Compose file."];
                    }
                }
            }
        } catch (\Exception $e) {
            // If there's an error parsing the Docker Compose file, we'll assume it's not used
            return [false, ''];
        }

        return [false, ''];
    }

    /**
     * Extract all environment variable keys defined in a Docker Compose file's environment sections.
     * Supports both list format (`- KEY=value`) and map format (`KEY: value`).
     *
     * @return array<string>
     */
    protected function extractDockerComposeEnvKeys(?string $dockerCompose): array
    {
        if (empty($dockerCompose)) {
            return [];
        }

        $keys = [];

        try {
            $dockerComposeData = Yaml::parse($dockerCompose);
            $servicesEnvs = data_get($dockerComposeData, 'services.*.environment', []);

            foreach ($servicesEnvs as $serviceEnvs) {
                if (! is_array($serviceEnvs)) {
                    continue;
                }

                foreach ($serviceEnvs as $envKey => $envValue) {
                    if (is_int($envKey) && is_string($envValue)) {
                        // List format: - KEY=value or - KEY
                        $equalsPos = strpos($envValue, '=');
                        $parsedKey = $equalsPos !== false ? substr($envValue, 0, $equalsPos) : $envValue;
                        $keys[] = trim($parsedKey);
                    } else {
                        // Map format: KEY: value
                        $keys[] = (string) $envKey;
                    }
                }
            }
        } catch (\Exception $e) {
            // Return empty if the compose file cannot be parsed
        }

        return array_unique($keys);
    }

    /**
     * Extract all environment variable keys defined via ENV instructions in a Dockerfile.
     * Supports both new syntax (`ENV KEY=VALUE`) and legacy syntax (`ENV KEY VALUE`).
     *
     * @return array<string>
     */
    protected function extractDockerfileEnvKeys(?string $dockerfile): array
    {
        if (empty($dockerfile)) {
            return [];
        }

        $keys = [];
        $lines = explode("\n", $dockerfile);

        foreach ($lines as $line) {
            $line = trim($line);

            if (! preg_match('/^ENV\s+(.+)$/i', $line, $matches)) {
                continue;
            }

            $envPart = trim($matches[1]);

            if (str_contains($envPart, '=')) {
                // New syntax: ENV KEY=VALUE (possibly multiple pairs on one line)
                preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)=/', $envPart, $keyMatches);
                foreach ($keyMatches[1] as $key) {
                    $keys[] = $key;
                }
            } else {
                // Legacy syntax: ENV KEY VALUE
                $parts = preg_split('/\s+/', $envPart, 2);
                if (! empty($parts[0])) {
                    $keys[] = $parts[0];
                }
            }
        }

        return array_unique($keys);
    }
}
