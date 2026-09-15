#!/usr/bin/env bash
set -euo pipefail

usage() {
    echo "Usage: $0 <Oracle-public-IP-or-hostname> <certificate-email>" >&2
    exit 1
}

[[ "$#" -eq 2 ]] || usage

host_input="${1#https://}"
host_input="${host_input%/}"
certificate_email="$2"

if [[ "${host_input}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
    IFS='.' read -r -a octets <<<"${host_input}"

    for octet in "${octets[@]}"; do
        (( octet >= 0 && octet <= 255 )) || usage
    done

    brave_hostname="${host_input//./-}.sslip.io"
else
    [[ "${host_input}" =~ ^[A-Za-z0-9.-]+$ ]] || usage
    brave_hostname="${host_input}"
fi

script_directory="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
environment_file="${script_directory}/.env"
example_file="${script_directory}/.env.example"

if [[ -e "${environment_file}" ]]; then
    echo "${environment_file} already exists; it was not overwritten." >&2
    exit 1
fi

cp "${example_file}" "${environment_file}"
chmod 600 "${environment_file}"

app_key="base64:$(openssl rand -base64 32 | tr -d '\n')"
database_password="$(openssl rand -hex 32)"

sed -i \
    -e "s|CHANGE_ME_HOSTNAME|${brave_hostname}|g" \
    -e "s|CHANGE_ME_EMAIL|${certificate_email}|g" \
    -e "s|CHANGE_ME_APP_KEY|${app_key}|g" \
    -e "s|CHANGE_ME_DB_PASSWORD|${database_password}|g" \
    "${environment_file}"

echo "Created ${environment_file} with generated Laravel and database secrets."
echo "API URL: https://${brave_hostname}"
echo "Edit the remaining CHANGE_ME values before deploying."
