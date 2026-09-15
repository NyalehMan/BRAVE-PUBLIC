# BRAVE Public on Oracle Cloud Always Free

This deployment keeps the public Vue site on GitHub Pages and runs Laravel,
PostgreSQL, incident-report photo storage, the MapTiler-enabled full app, and
the official JASTRe PSI reader on one Oracle Cloud VM.

## What this repository now provides

- `deploy/oracle/compose.yaml`: Laravel, private PostgreSQL, and Caddy HTTPS.
- `deploy/oracle/prepare-env.sh`: creates an untracked production environment
  file and generates the Laravel/database secrets.
- `deploy/oracle/deploy.sh`: refuses placeholder secrets, validates Compose,
  builds the ARM-compatible image, migrates the database, and starts services.
- `deploy/oracle/backup.sh`: exports PostgreSQL and report-photo storage.
- `deploy/oracle/install-docker-ubuntu.sh`: installs Docker Engine and Compose
  from Docker's official Ubuntu repository.

The real `deploy/oracle/.env` is ignored by Git and must exist only on the VM.

## 1. Create only an Always Free VM

In the Oracle Cloud Console, create a compute instance with:

- Image: Ubuntu 24.04, marked **Always Free Eligible**.
- Shape: `VM.Standard.A1.Flex`, marked **Always Free Eligible**.
- Resources: **2 OCPUs and 12 GB RAM total**.
- Boot volume: the default size (about 47-50 GB), within the tenancy's 200 GB
  Always Free block-volume allowance.
- Networking: public IPv4 enabled.
- SSH: add a dedicated public key whose private half remains off GitHub.

Never continue if the estimate is not zero or the selected image/shape is not
explicitly marked Always Free Eligible.

In the instance's VCN security list or Network Security Group, allow:

| Source | Protocol | Port | Purpose |
|---|---|---:|---|
| Your current public IP `/32` | TCP | 22 | SSH administration |
| `0.0.0.0/0` | TCP | 80 | ACME certificate validation and HTTP redirect |
| `0.0.0.0/0` | TCP | 443 | Public HTTPS API |
| `0.0.0.0/0` | UDP | 443 | Optional HTTP/3 |

Do not expose PostgreSQL port 5432 or the app's internal port 8080.

## 2. Install and prepare BRAVE

SSH to the VM as the `ubuntu` user, then run:

```bash
git clone https://github.com/NyalehMan/BRAVE-PUBLIC.git
cd BRAVE-PUBLIC
sudo bash deploy/oracle/install-docker-ubuntu.sh
```

Sign out and reconnect once so the Docker group applies. Then create the
environment file. Supplying the Oracle public IP automatically creates a free
DNS hostname such as `129-146-1-20.sslip.io`:

```bash
cd BRAVE-PUBLIC
./deploy/oracle/prepare-env.sh YOUR_ORACLE_PUBLIC_IP YOUR_EMAIL_ADDRESS
nano deploy/oracle/.env
```

Replace all remaining `CHANGE_ME` values with the existing MapTiler and ArcGIS
production values. Restrict the browser-visible MapTiler key to:

```text
https://nyalehman.github.io/*
https://YOUR_SSLIP_HOSTNAME/*
```

If the ArcGIS token is referrer-restricted, add the exact Oracle HTTPS origin
shown by `prepare-env.sh` to that token's allowed referrers.

## 3. Deploy and verify

```bash
./deploy/oracle/deploy.sh
```

The first ARM build can take several minutes. When it finishes, verify these
URLs in a browser:

```text
https://YOUR_SSLIP_HOSTNAME/up
https://YOUR_SSLIP_HOSTNAME/api/public/psi
https://YOUR_SSLIP_HOSTNAME/api/public/incidents/ongoing
```

The health endpoint should return HTTP 200. The PSI endpoint should return four
official district readings. The incidents endpoint should return JSON.

For logs:

```bash
docker compose --env-file deploy/oracle/.env -f deploy/oracle/compose.yaml logs --tail=200
```

## 4. Point GitHub Pages at Oracle

In `NyalehMan/BRAVE-PUBLIC`, open **Settings > Secrets and variables > Actions >
Variables** and set:

```text
VITE_API_BASE_URL=https://YOUR_SSLIP_HOSTNAME
```

Do not append `/api`. Then open **Actions > Deploy BRAVE public view to GitHub
Pages > Run workflow**. The public site will be:

```text
https://nyalehman.github.io/BRAVE-PUBLIC/
```

## Updating and backing up

To deploy a later commit:

```bash
git pull --ff-only
./deploy/oracle/deploy.sh
```

To back up the database and private incident-report photos:

```bash
./deploy/oracle/backup.sh
```

Copy the resulting files from `deploy/oracle/backups/` to another protected
location. A backup kept only on the VM does not protect against VM or account
loss.
