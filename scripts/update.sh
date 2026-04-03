#!/bin/bash
## Hetzner update script for existing custom Coolify installation
## Run as root on the server where CPM is already installed:
##   curl -fsSL https://raw.githubusercontent.com/pigmilcom/cpm/v4.x/scripts/update-hetzner.sh | bash

set -euo pipefail

GITHUB_REPO="https://github.com/pigmilcom/cpm.git"
BRANCH="v4.x"
INSTALL_DIR="/data/coolify/source"
ENV_FILE="$INSTALL_DIR/.env"
DATE=$(date +"%Y%m%d-%H%M%S")
BACKUP_DIR="/data/coolify/backups/manual-update-$DATE"

compose_cmd() {
    docker compose \
        -f "$INSTALL_DIR/docker-compose.yml" \
        -f "$INSTALL_DIR/docker-compose.prod.yml" \
        -f "$INSTALL_DIR/docker-compose.source.prod.yml" \
        --env-file "$ENV_FILE" "$@"
}

ensure_git_safe_directory() {
    git config --global --add safe.directory "$INSTALL_DIR"
}

merge_new_env_keys() {
    if [ -f "$ENV_FILE" ] && [ -f "$INSTALL_DIR/.env.production" ]; then
        awk -F '=' '!seen[$1]++' "$ENV_FILE" "$INSTALL_DIR/.env.production" > "$ENV_FILE.tmp"
        mv "$ENV_FILE.tmp" "$ENV_FILE"
    fi
}

set_env_if_empty_or_missing() {
    local key="$1"
    local value="$2"

    if grep -q "^${key}=$" "$ENV_FILE" 2>/dev/null; then
        sed -i "s|^${key}=$|${key}=${value}|" "$ENV_FILE"
    elif ! grep -q "^${key}=" "$ENV_FILE" 2>/dev/null; then
        echo "${key}=${value}" >> "$ENV_FILE"
    fi
}

echo ""
echo "============================================================"
echo "  CPM (Custom Coolify) - Hetzner Update"
echo "  $(date)"
echo "============================================================"
echo ""

if [ "$EUID" -ne 0 ]; then
    echo "Please run as root or with sudo."
    exit 1
fi

if [ ! -d "$INSTALL_DIR/.git" ]; then
    echo "ERROR: Existing installation not found at $INSTALL_DIR"
    echo "Run deploy script first:"
    echo "  curl -fsSL https://raw.githubusercontent.com/pigmilcom/cpm/v4.x/scripts/deploy-hetzner.sh | bash"
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "ERROR: Docker is not installed or not in PATH"
    exit 1
fi

if ! command -v docker compose >/dev/null 2>&1; then
    echo "ERROR: Docker Compose plugin is not installed"
    exit 1
fi

ensure_git_safe_directory

echo "[1/7] Preparing backup metadata..."
mkdir -p "$BACKUP_DIR"

echo " - Saving current commit / branch info"
git -C "$INSTALL_DIR" rev-parse HEAD > "$BACKUP_DIR/previous_commit.txt" || true
git -C "$INSTALL_DIR" branch --show-current > "$BACKUP_DIR/previous_branch.txt" || true

if [ -f "$ENV_FILE" ]; then
    cp "$ENV_FILE" "$BACKUP_DIR/.env.before-update"
fi

echo "[2/7] Pulling latest code from GitHub..."
git -C "$INSTALL_DIR" fetch origin
git -C "$INSTALL_DIR" reset --hard "origin/$BRANCH"

echo "[3/7] Ensuring environment file is compatible..."
if [ ! -f "$ENV_FILE" ]; then
    cp "$INSTALL_DIR/.env.production" "$ENV_FILE"
fi

merge_new_env_keys

set_env_if_empty_or_missing "APP_ENV" "production"
set_env_if_empty_or_missing "APP_DEBUG" "false"
set_env_if_empty_or_missing "APP_NAME" "Coolify"
set_env_if_empty_or_missing "APP_PORT" "8000"
set_env_if_empty_or_missing "DB_USERNAME" "coolify"
set_env_if_empty_or_missing "DB_DATABASE" "coolify"
set_env_if_empty_or_missing "REGISTRY_URL" "ghcr.io"
set_env_if_empty_or_missing "AUTOUPDATE" "true"
set_env_if_empty_or_missing "DOCKER_ADDRESS_POOL_BASE" "10.0.0.0/8"
set_env_if_empty_or_missing "DOCKER_ADDRESS_POOL_SIZE" "24"
set_env_if_empty_or_missing "DOCKER_POOL_FORCE_OVERRIDE" "false"

echo "[4/7] Creating required docker network (if missing)..."
if ! docker network inspect coolify >/dev/null 2>&1; then
    docker network create coolify >/dev/null
fi

echo "[5/7] Rebuilding and restarting services (data-safe)..."
compose_cmd up -d --build --pull always coolify postgres redis soketi

echo "[6/7] Running database migrations..."
MAX_CONTAINER_WAIT=60
CONTAINER_WAITED=0
until docker exec coolify php artisan migrate --force 2>/dev/null; do
    CONTAINER_STATE=$(docker inspect --format='{{.State.Status}}' coolify 2>/dev/null || echo "missing")
    if [ "$CONTAINER_WAITED" -ge "$MAX_CONTAINER_WAIT" ]; then
        echo "ERROR: Could not run migrations after ${MAX_CONTAINER_WAIT}s (container state: $CONTAINER_STATE)."
        echo "Check logs: docker logs coolify"
        exit 1
    fi
    echo " - Waiting for coolify container to accept commands... (state: $CONTAINER_STATE, ${CONTAINER_WAITED}s)"
    sleep 3
    CONTAINER_WAITED=$((CONTAINER_WAITED + 3))
done
echo " - Migrations completed."

echo "[7/7] Waiting for app health..."
MAX_WAIT=180
WAITED=0
while [ "$WAITED" -lt "$MAX_WAIT" ]; do
    HEALTH=$(docker inspect --format='{{.State.Health.Status}}' coolify 2>/dev/null || echo "starting")
    echo " - coolify: $HEALTH  (${WAITED}s / ${MAX_WAIT}s)"
    if [ "$HEALTH" = "healthy" ]; then
        break
    fi
    sleep 5
    WAITED=$((WAITED + 5))
done

if [ "$HEALTH" != "healthy" ]; then
    echo ""
    echo "WARNING: Coolify did not become healthy in ${MAX_WAIT}s."
    echo "Check logs: docker logs coolify"
    echo "Previous commit is saved in: $BACKUP_DIR/previous_commit.txt"
    exit 1
fi

PUBLIC_IP=$(curl -4s --max-time 5 https://ifconfig.io 2>/dev/null || echo "<your-server-ip>")

echo ""
echo "============================================================"
echo " Update complete (data preserved)."
echo " App URL: http://$PUBLIC_IP:8000"
echo " Backup metadata: $BACKUP_DIR"
echo "============================================================"
echo ""
echo "Useful commands:"
echo "  docker logs -f coolify"
echo "  docker exec -it coolify php artisan about"
echo "  docker compose -f $INSTALL_DIR/docker-compose.yml -f $INSTALL_DIR/docker-compose.prod.yml -f $INSTALL_DIR/docker-compose.source.prod.yml ps"
