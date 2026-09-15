#!/usr/bin/env bash
set -euo pipefail

script_directory="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
environment_file="${script_directory}/.env"
compose_file="${script_directory}/compose.yaml"
backup_directory="${script_directory}/backups"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"

mkdir -p "${backup_directory}"
chmod 700 "${backup_directory}"

docker compose \
    --env-file "${environment_file}" \
    -f "${compose_file}" \
    exec -T database \
    sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip >"${backup_directory}/database-${timestamp}.sql.gz"

docker run --rm \
    --volume brave-public-storage:/source:ro \
    --volume "${backup_directory}:/backup" \
    alpine:3.22 \
    tar -czf "/backup/report-storage-${timestamp}.tar.gz" -C /source .

echo "Backup written to ${backup_directory}. Copy it off the VM for disaster recovery."
