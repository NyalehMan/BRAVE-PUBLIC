# BRAVE backend — after copying

Requirements: PHP 8.3 or newer and the PHP extensions required by Laravel.

From this `backend` folder, run:

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list
php artisan serve --host=127.0.0.1 --port=8000
```

Keep the server window open. The compiled frontend is already present in
`public`, so `http://127.0.0.1:8000` can serve the complete application.

The existing `.env` from the supplied project is retained. For a new
environment, compare it with `.env.example`, especially `FRONTEND_URL`,
`SANCTUM_STATEFUL_DOMAINS`, `ARCGIS_FIRE_INCIDENT_LAYER_URL`, `ARCGIS_TOKEN`,
and `ARCGIS_REFERER`.
