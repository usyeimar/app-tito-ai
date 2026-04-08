#!/usr/bin/env bash
set -euo pipefail

# === Configuration ===
# Must match docker/nginx/nginx.conf and README instructions.
DOMAIN="app.tito.ai"
SSL_DIR=".certs"

# Certificate file paths
CRT="$SSL_DIR/${DOMAIN}.pem"
KEY="$SSL_DIR/${DOMAIN}-key.pem"

# === Colors ===
GREEN="\033[1;32m"
YELLOW="\033[1;33m"
RED="\033[1;31m"
BLUE="\033[1;34m"
RESET="\033[0m"

mkdir -p "$SSL_DIR"

echo -e "${BLUE}[INFO] Checking for mkcert...${RESET}"

# === Install mkcert if missing ===
run_with_privileges() {
  if [[ $(id -u) -eq 0 ]]; then
    "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo "$@"
  else
    echo -e "${RED}[ERROR] Root privileges required to install mkcert. Run this script as root or install mkcert manually:${RESET}"
    echo "https://github.com/FiloSottile/mkcert"
    exit 1
  fi
}

if ! command -v mkcert >/dev/null 2>&1; then
  echo -e "${YELLOW}[WARN] mkcert not found. Attempting automatic install...${RESET}"

  if command -v apt-get >/dev/null 2>&1; then
    run_with_privileges apt-get update -y
    run_with_privileges apt-get install -y mkcert libnss3-tools
  elif command -v dnf >/dev/null 2>&1; then
    run_with_privileges dnf install -y mkcert nss-tools
  elif command -v yum >/dev/null 2>&1; then
    run_with_privileges yum install -y mkcert nss-tools
  elif command -v pacman >/dev/null 2>&1; then
    run_with_privileges pacman -Sy --noconfirm mkcert nss
  elif command -v zypper >/dev/null 2>&1; then
    run_with_privileges zypper install -y mkcert mozilla-nss-tools
  elif command -v apk >/dev/null 2>&1; then
    run_with_privileges apk add --no-cache mkcert nss-tools
  elif command -v brew >/dev/null 2>&1; then
    brew install mkcert nss
  else
    echo -e "${RED}[ERROR] Could not install mkcert automatically. Please install manually:${RESET}"
    echo "https://github.com/FiloSottile/mkcert"
    exit 1
  fi

  echo -e "${GREEN}[OK] mkcert installed successfully.${RESET}"
fi

# === Local CA ===
echo -e "${BLUE}[INFO] mkcert found. Installing local CA...${RESET}"
mkcert -install >/dev/null 2>&1 || true

# === Generate certificates ===
if [[ ! -f "$CRT" || ! -f "$KEY" ]]; then
  echo -e "${BLUE}[INFO] Generating trusted certificate for ${DOMAIN}...${RESET}"
  mkcert -key-file "$KEY" -cert-file "$CRT" "$DOMAIN"
  echo -e "${GREEN}[OK] Certificate generated: $CRT${RESET}"
else
  echo -e "${YELLOW}[INFO] Certificate for ${DOMAIN} already exists. Skipping...${RESET}"
fi

# === Reminders ===
if ! grep -qE "^[[:space:]]*127\\.0\\.0\\.1[[:space:]]+$DOMAIN" /etc/hosts 2>/dev/null; then
  echo -e "${YELLOW}[NOTE] Add to /etc/hosts if not present:${RESET} 127.0.0.1 $DOMAIN"
fi

echo -e "${YELLOW}[NOTE] Ensure APP_URL is set:${RESET} APP_URL=https://$DOMAIN"
echo -e "${YELLOW}[NOTE] Restart Sail/nginx if already running:${RESET} ./vendor/bin/sail restart"
echo -e "${GREEN}[OK] Certificates ready in $SSL_DIR${RESET}"

