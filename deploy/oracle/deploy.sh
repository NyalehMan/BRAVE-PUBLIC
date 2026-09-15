#!/usr/bin/env bash
set -euo pipefail

script_directory="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
environment_file="${script_directory}/.env"
compose_file="${script_directory}/compose.yaml"

if [[ ! -f "${environment_file}" ]]; then
    echo "Run prepare-env.sh first; ${environment_file} does not exist." >&2
    exit 1
fi

if grep -Eq '(^|=)CHANGE_ME' "${environment_file}"; then
    echo "Deployment stopped: replace every CHANGE_ME value in ${environment_file}." >&2
    exit 1
fi

docker compose \
    --env-file "${environment_file}" \
    -f "${compose_file}" \
    config --quiet

docker compose \
    --env-file "${environment_file}" \
    -f "${compose_file}" \
    up -d --build --remove-orphans

docker compose \
    --env-file "${environment_file}" \
    -f "${compose_file}" \
    ps

echo "Deployment started. Caddy will obtain HTTPS after ports 80 and 443 are reachable."
