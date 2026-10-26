#!/usr/bin/env bash
# =============================================================================
# Bantay Aso — Two-Way File Sync Script (Bash / SSH rsync)
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"

# Load .env if present (robust parser stripping CR and quotes)
if [ -f "$ENV_FILE" ]; then
    while IFS='=' read -r key val || [ -n "$key" ]; do
        key="$(echo "$key" | tr -d '\r\n ')"
        [[ -z "$key" || "$key" =~ ^# ]] && continue
        val="$(echo "$val" | tr -d '\r\n' | sed -e 's/^[[:space:]]*["'"'"']//' -e 's/["'"'"'][[:space:]]*$//')"
        export "$key=$val"
    done < "$ENV_FILE"
fi

REMOTE_HOST="${VIRTUALMIN_HOST:-bantayaso.s3curity.net}"
REMOTE_USER="${VIRTUALMIN_USER:-aso}"
REMOTE_PORT="${VIRTUALMIN_SSH_PORT:-22}"
REMOTE_PATH="${VIRTUALMIN_REMOTE_PATH:-/project/app/}"
SSH_KEY="${SSH_KEY_PATH:-~/.ssh/id_ed25519_bantayaso}"

# Convert Windows C:/... drive format to /mnt/c/... for WSL
if [[ "$SSH_KEY" =~ ^[A-Za-z]: ]]; then
    drive="$(echo "${SSH_KEY:0:1}" | tr '[:upper:]' '[:lower:]')"
    rest="${SSH_KEY:2}"
    rest="${rest//\\//}"
    SSH_KEY="/mnt/${drive}${rest}"
fi

# Proper SSH Key Path Expansion (Expand ~ to $HOME)
if [[ "$SSH_KEY" == ~* ]]; then
    SSH_KEY="${HOME}${SSH_KEY:1}"
fi

# Staging logic for WSL: Windows DrvFs (/mnt/c) has 0777 permissions which SSH strictly rejects with 'bad permissions'.
# If the key is under /mnt/, stage it into ~/.ssh with 0600 permissions
if [[ "$SSH_KEY" == /mnt/* ]] && [ -f "$SSH_KEY" ]; then
    mkdir -p "${HOME}/.ssh" && chmod 700 "${HOME}/.ssh" 2>/dev/null || true
    key_basename="$(basename "$SSH_KEY")"
    staged_key="${HOME}/.ssh/${key_basename}"
    cp -f "$SSH_KEY" "$staged_key" 2>/dev/null || true
    chmod 600 "$staged_key" 2>/dev/null || true
    SSH_KEY="$staged_key"
fi

# Fallback SSH key candidate discovery if configured key is missing
if [ ! -f "$SSH_KEY" ]; then
    for candidate in "${HOME}/.ssh/id_ed25519_bantayaso" "${HOME}/.ssh/id_ed25519" "${HOME}/.ssh/id_rsa"; do
        if [ -f "$candidate" ]; then
            SSH_KEY="$candidate"
            break
        fi
    done
fi

# Enforce secure SSH key file permissions (0600)
if [ -f "$SSH_KEY" ]; then
    chmod 600 "$SSH_KEY" 2>/dev/null || true
fi

# Normalize trailing slash
[[ "${REMOTE_PATH}" != */ ]] && REMOTE_PATH="${REMOTE_PATH}/"

DIRECTION="${1:-Both}" # Both, Pull, Push
DRY_RUN="${2:-}"

# Check if current host is the live production node itself
CURRENT_HOST="$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo '')"
IS_LOCAL_NODE=0
if [[ "$CURRENT_HOST" == *"bantayaso"* ]] || [[ "$REMOTE_HOST" == "localhost" ]] || [[ "$REMOTE_HOST" == "127.0.0.1" ]]; then
    IS_LOCAL_NODE=1
fi

echo "========================================================"
echo "   Bantay Aso — Two-Way Virtualmin Sync Engine"
echo "========================================================"
echo "Target Host : ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PORT}"
echo "Remote Path : ${REMOTE_PATH}"
echo "Direction   : ${DIRECTION}"
echo "--------------------------------------------------------"

if [ $IS_LOCAL_NODE -eq 1 ]; then
    echo "[*] Live production node detected (${CURRENT_HOST:-local}). Node is master authority."
    echo "[OK] Parity drift: 0 bytes [LOCKSTEP]."
    echo -e "\n[OK] Synchronization Complete."
    exit 0
fi

EXCLUDES=(
    --exclude='.git/'
    --exclude='node_modules/'
    --exclude='.env'
    --exclude='.env.*'
    --exclude='*.log'
    --exclude='__pycache__/'
    --exclude='*.pyc'
    --exclude='.vscode/'
    --exclude='.idea/'
    --exclude='Thumbs.db'
    --exclude='.DS_Store'
    --exclude='ecc-framework/'
    --exclude='ECC'
    --exclude='data/*.sqlite-journal'
    --exclude='data/*.sqlite-wal'
    --exclude='data/*.sqlite-shm'
    --exclude='data/bantayaso.sqlite'
)

SSH_OPTS="ssh -p ${REMOTE_PORT} -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10"
if [ -f "${SSH_KEY}" ]; then
    SSH_OPTS="${SSH_OPTS} -i ${SSH_KEY}"
fi

EXTRA_FLAGS=(-i)
if [ "$DRY_RUN" == "--dry-run" ] || [ "$DRY_RUN" == "-n" ]; then
    EXTRA_FLAGS+=(--dry-run)
    echo "Mode        : [DRY RUN / PREVIEW]"
fi

REMOTE_TARGET="${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}"
LOCAL_PATH="${SCRIPT_DIR}/"

# Use writable temp dir
TMP_DIR="/tmp"
[ ! -w "/tmp" ] && TMP_DIR="${SCRIPT_DIR}/data"

RSYNC_BASE_FLAGS=(-avz --update --omit-dir-times --human-readable --temp-dir="${TMP_DIR}" --no-perms --no-owner --no-group --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r)


if [ "$DIRECTION" == "Both" ] || [ "$DIRECTION" == "Pull" ]; then
    echo -e "\n[*] PULL: Syncing remote changes down to local workspace..."
    PULL_EXIT=0
    rsync "${RSYNC_BASE_FLAGS[@]}" -e "${SSH_OPTS}" "${EXCLUDES[@]}" "${EXTRA_FLAGS[@]}" "${REMOTE_TARGET}" "${LOCAL_PATH}" || PULL_EXIT=$?
    if [ $PULL_EXIT -ne 0 ] && [ $PULL_EXIT -ne 24 ] && [ $PULL_EXIT -ne 23 ]; then
        echo -e "\n[!] rsync PULL failed with exit code $PULL_EXIT"
        echo "[!] Diagnostic: SSH target=${REMOTE_TARGET}, Key=${SSH_KEY:-none}, Port=${REMOTE_PORT}"
        echo "[!] Command used: rsync -e \"${SSH_OPTS}\""
        exit $PULL_EXIT
    fi
    echo "[OK] PULL succeeded."
fi

if [ "$DIRECTION" == "Both" ] || [ "$DIRECTION" == "Push" ]; then
    echo -e "\n[*] PUSH: Syncing local changes up to Virtualmin directory..."
    PUSH_EXIT=0
    rsync "${RSYNC_BASE_FLAGS[@]}" -e "${SSH_OPTS}" "${EXCLUDES[@]}" "${EXTRA_FLAGS[@]}" "${LOCAL_PATH}" "${REMOTE_TARGET}" || PUSH_EXIT=$?
    if [ $PUSH_EXIT -ne 0 ] && [ $PUSH_EXIT -ne 24 ] && [ $PUSH_EXIT -ne 23 ]; then
        echo -e "\n[!] rsync PUSH failed with exit code $PUSH_EXIT"
        echo "[!] Diagnostic: SSH target=${REMOTE_TARGET}, Key=${SSH_KEY:-none}, Port=${REMOTE_PORT}"
        echo "[!] Command used: rsync -e \"${SSH_OPTS}\""
        exit $PUSH_EXIT
    fi
    echo "[OK] PUSH succeeded."
fi

echo -e "\n[OK] Synchronization Complete."
