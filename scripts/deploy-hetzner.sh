#!/bin/bash
## Hetzner production deployment script for custom Coolify (pigmilcom/cpm)
## Run as root on a fresh Debian/Ubuntu Hetzner server:
##   curl -fsSL https://raw.githubusercontent.com/pigmilcom/cpm/v4.x/scripts/deploy-hetzner.sh | bash

set -euo pipefail

GITHUB_REPO="https://github.com/pigmilcom/cpm.git"
BRANCH="v4.x"
INSTALL_DIR="/opt/cpm"
ENV_FILE="$INSTALL_DIR/.env"
DATE=$(date +"%Y%m%d-%H%M%S")

echo ""
echo "============================================================"
echo "  CPM (Custom Coolify) - Hetzner Production Deployment"
echo "  $(date)"
echo "============================================================"
echo ""

if [ "$EUID" -ne 0 ]; then
    echo "Please run as root or with sudo."
    exit 1
fi

# ─── 1. System packages ───────────────────────────────────────────────────────
echo "[1/6] Installing system packages..."
apt-get update -qq
apt-get install -y -qq curl wget git jq openssl ca-certificates gnupg lsb-release

# ─── 2. Docker ────────────────────────────────────────────────────────────────
echo "[2/6] Installing Docker..."
if ! command -v docker &>/dev/null; then
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
    echo " - Docker installed."
else
    echo " - Docker already installed."
fi

if ! command -v docker compose &>/dev/null; then
    # Docker Compose v2 plugin
    mkdir -p /usr/local/lib/docker/cli-plugins
    curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" \
        -o /usr/local/lib/docker/cli-plugins/docker-compose
    chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
    echo " - Docker Compose installed."
fi

# ─── 3. Clone / update repo ───────────────────────────────────────────────────
echo "[3/6] Cloning repo: $GITHUB_REPO ($BRANCH)..."
if [ -d "$INSTALL_DIR/.git" ]; then
    echo " - Repo already exists, pulling latest..."
    git -C "$INSTALL_DIR" fetch origin
    git -C "$INSTALL_DIR" reset --hard "origin/$BRANCH"
else
    git clone --branch "$BRANCH" --depth 1 "$GITHUB_REPO" "$INSTALL_DIR"
fi
echo " - Done."

# ─── 4. Environment file ──────────────────────────────────────────────────────
echo "[4/6] Setting up .env..."
if [ -f "$ENV_FILE" ]; then
    echo " - Backing up existing .env to .env.$DATE"
    cp "$ENV_FILE" "$ENV_FILE.$DATE"
    # Merge any new keys from .env.production without overwriting existing values
    awk -F '=' '!seen[$1]++' "$ENV_FILE" "$INSTALL_DIR/.env.production" > "$ENV_FILE.tmp" && mv "$ENV_FILE.tmp" "$ENV_FILE"
else
    cp "$INSTALL_DIR/.env.production" "$ENV_FILE"
fi

# Helper: set key only if currently empty or missing
set_env() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=$" "$ENV_FILE" 2>/dev/null; then
        sed -i "s|^${key}=$|${key}=${value}|" "$ENV_FILE"
        echo " - Set $key"
    elif ! grep -q "^${key}=" "$ENV_FILE" 2>/dev/null; then
        echo "${key}=${value}" >> "$ENV_FILE"
        echo " - Added $key"
    fi
}

set_env "APP_ENV"        "production"
set_env "APP_DEBUG"      "false"
set_env "APP_ID"         "$(openssl rand -hex 16)"
set_env "APP_KEY"        "base64:$(openssl rand -base64 32)"
set_env "DB_PASSWORD"    "$(openssl rand -base64 32)"
set_env "REDIS_PASSWORD" "$(openssl rand -base64 32)"
set_env "PUSHER_APP_ID"  "$(openssl rand -hex 32)"
set_env "PUSHER_APP_KEY" "$(openssl rand -hex 32)"
set_env "PUSHER_APP_SECRET" "$(openssl rand -hex 32)"

# Set APP_URL  to the server's public IP if not already set
if grep -q "^APP_URL=$" "$ENV_FILE" 2>/dev/null || ! grep -q "^APP_URL=" "$ENV_FILE" 2>/dev/null; then
    PUBLIC_IP=$(curl -4s --max-time 5 https://ifconfig.io || true)
    if [ -n "$PUBLIC_IP" ]; then
        set_env "APP_URL" "http://$PUBLIC_IP:8000"
    fi
fi

set_env "APP_PORT"       "8000"
set_env "APP_NAME"       "Coolify"
set_env "SSH_MUX_ENABLED" "false"

echo " - .env ready."

# ─── 5. Required data directories ─────────────────────────────────────────────
echo "[5/6] Creating data directories..."
mkdir -p /data/coolify/{source,ssh/keys,ssh/mux,applications,databases,backups,services,proxy/dynamic,sentinel}
chown -R 9999:root /data/coolify 2>/dev/null || true
chmod -R 700 /data/coolify

# ─── 6. Build & launch ────────────────────────────────────────────────────────
echo "[6/6] Building and starting CPM..."
cd "$INSTALL_DIR"

# We build from source (dev Dockerfile) so your custom code is what runs.
# docker-compose.prod.yml uses a pre-built ghcr.io image, so we use the dev
# build config instead, with production env values from .env.
docker compose \
    -f docker-compose.yml \
    -f docker-compose.dev.yml \
    --env-file "$ENV_FILE" \
    up -d --build \
    coolify postgres redis soketi

echo ""
echo "============================================================"
echo " Waiting for services to become healthy..."
echo "============================================================"

MAX_WAIT=120
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
    echo "WARNING: Container did not reach healthy state in ${MAX_WAIT}s."
    echo "Check logs with:  docker logs coolify"
else
    PUBLIC_IP=$(curl -4s --max-time 5 https://ifconfig.io 2>/dev/null || echo "<your-server-ip>")
    echo ""
    echo "============================================================"
    echo " CPM is running!"
    echo " Open: http://$PUBLIC_IP:8000"
    echo "============================================================"
    echo ""
    echo " Useful commands:"
    echo "   docker logs -f coolify"
    echo "   docker exec -it coolify php artisan migrate"
    echo "   docker compose -f $INSTALL_DIR/docker-compose.yml -f $INSTALL_DIR/docker-compose.dev.yml ps"
    echo ""
    echo " WARNING: Back up $ENV_FILE to a safe location!"
fi
