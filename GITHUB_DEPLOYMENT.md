# BRAVE GitHub deployment

GitHub stores the BRAVE source, builds the public frontend, and publishes a
backup full-stack container image. GitHub Pages cannot execute PHP, run
Tesseract, or provide a database, writable incident-report storage, queues, or
a continuously running API. The included `render.yaml` Blueprint deploys those
services to Render directly from this repository.

## Recommended production layout

- `https://nyalehman.github.io/BRAVE-PUBLIC/`: public-only Vue frontend on
  GitHub Pages.
- `https://brave-public-api-nyalehman.onrender.com`: Laravel API and same-origin
  full application on Render.
- Render PostgreSQL: incident reports, users, sessions, cache, and queues.
- Persistent volume mounted at `/var/www/html/storage`: private incident-report
  photos. Do not use an ephemeral filesystem for evidence uploads.
- `ghcr.io/nyalehman/brave-public:latest`: backup full-stack image built by
  GitHub Actions. Render builds from the repository's Dockerfile so it does not
  depend on the GitHub package's visibility.

The same-origin container deployment is the safest choice for the NIAT reviewer
login because browsers increasingly block cross-site session cookies.

## GitHub repository configuration

In **Settings > Secrets and variables > Actions**, create:

### Repository secret

- `VITE_MAPTILER_API_KEY`: a MapTiler browser API key. This value is embedded in
  the browser bundle by design. Restrict the key in MapTiler to the exact public
  origin, such as `https://nyalehman.github.io` and the production application
  domain.

### Repository variable

- `VITE_API_BASE_URL`: the HTTPS origin of the running Laravel API, without a
  trailing `/api`, for example `https://api.example.com`.

In **Settings > Pages**, select **GitHub Actions** as the publishing source.
The Pages workflow intentionally builds only the public fire dashboard routes.
Until `VITE_API_BASE_URL` has been set, its build job stays skipped instead of
publishing a dashboard whose API calls cannot work.

## Render Blueprint

The root `render.yaml` defines the Docker web service, a 1 GB persistent upload
disk, and a 1 GB PostgreSQL database in Singapore. It uses the smallest paid
production plans because Render does not allow persistent disks on free web
services and free databases expire.

At current Render pricing, the configured minimum is approximately $13.55 USD
per month before bandwidth: $7 web compute, $6 PostgreSQL compute, $0.25 upload
storage, and $0.30 database storage. Review the price in Render before approving
the Blueprint.

Open the repository from **Render Dashboard > New > Blueprint**, or use:

https://render.com/deploy?repo=https://github.com/NyalehMan/BRAVE-PUBLIC

Render prompts for the five values marked `sync: false`:

- `APP_KEY`: output from `php backend/artisan key:generate --show`.
- `VITE_MAPTILER_API_KEY`: the same browser-restricted key stored in GitHub.
- `ARCGIS_FIRE_INCIDENT_LAYER_URL`: copied from the local backend `.env`.
- `ARCGIS_FLOOD_INCIDENT_LAYER_URL`: copied from the local backend `.env`.
- `ARCGIS_TOKEN`: copied from the local backend `.env`.

Although the MapTiler key is supplied as a secret, Vite embeds it in browser
JavaScript by design. Restrict it by allowed public origins in MapTiler.

## Runtime variables for another container host

Configure these on the container host; never commit their values:

```dotenv
APP_NAME=BRAVE
APP_ENV=production
APP_KEY=base64:GENERATE_A_REAL_LARAVEL_KEY
APP_DEBUG=false
APP_URL=https://brave-public-api-nyalehman.onrender.com

FRONTEND_URL=https://nyalehman.github.io/BRAVE-PUBLIC
CORS_ALLOWED_ORIGINS=https://nyalehman.github.io
SANCTUM_STATEFUL_DOMAINS=nyalehman.github.io,brave-public-api-nyalehman.onrender.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
SESSION_PARTITIONED_COOKIE=true

DB_CONNECTION=pgsql
DB_URL=postgresql://USER:PASSWORD@HOST:5432/DATABASE
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

REPORT_PHOTO_DISK=local
TESSERACT_PATH=/usr/bin/tesseract
RUN_MIGRATIONS=true

ARCGIS_FIRE_INCIDENT_LAYER_URL=https://services.example.com/FeatureServer/0
ARCGIS_FLOOD_INCIDENT_LAYER_URL=https://services.example.com/FeatureServer/0
ARCGIS_TOKEN=REPLACE_ON_THE_HOST
ARCGIS_REFERER=https://brave-public-api-nyalehman.onrender.com
```

Generate `APP_KEY` locally without displaying or committing the production
value:

```powershell
php backend/artisan key:generate --show
```

Store the resulting key directly in the container host's secret manager.

## What each workflow does

`deploy-public-pages.yml` builds the public-only Vue bundle with the repository
API URL and MapTiler key, then publishes it through GitHub Pages.

`publish-fullstack-image.yml` builds Vue, Laravel, Apache, PHP 8.4, and
Tesseract into one image and publishes it to GitHub Container Registry. A
container host must pull and run that image on port `8080`.

The container runs database migrations at startup unless
`RUN_MIGRATIONS=false`. Its `/up` endpoint is used as the health check.

## PSI requirements

`GET /api/public/psi` downloads the official JASTRe PSI image and uses
Tesseract OCR. The production image includes Tesseract plus PHP GD for image
preprocessing and configures `TESSERACT_PATH=/usr/bin/tesseract` through the
runtime environment. The API host must permit outbound HTTPS access to
`https://www.env.gov.bn/`.

## Deployment order

1. Add the MapTiler repository secret described above.
2. Commit and push this repository to `NyalehMan/BRAVE-PUBLIC`.
3. Deploy the `render.yaml` Blueprint and enter its prompted secret values.
4. Confirm `https://brave-public-api-nyalehman.onrender.com/up` is healthy.
5. Set the GitHub repository variable `VITE_API_BASE_URL` to
   `https://brave-public-api-nyalehman.onrender.com`.
6. Enable GitHub Pages from GitHub Actions and run the Pages workflow.
7. Verify `/api/public/psi` and a test incident submission before announcing
   the public URL.
