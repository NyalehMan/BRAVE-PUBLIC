# Host BRAVE Public locally with Tailscale Funnel

This deployment keeps the public Vue frontend on GitHub Pages while Laravel,
the existing MySQL database, incident-report photos, ArcGIS integration, and
the JASTRe PSI reader run on the Windows BRAVE computer.

Public traffic follows this path:

```text
GitHub Pages -> HTTPS Tailscale Funnel -> 127.0.0.1:8081 -> BRAVE container
                                                    -> existing MySQL service
```

The Docker port is bound only to Windows loopback. PostgreSQL/MySQL and the
home router are not exposed to the internet.

## Start

Docker Desktop, Tailscale, and the Windows MySQL service must be running.

```powershell
powershell.exe -ExecutionPolicy Bypass -File deploy\local\backup.ps1
powershell.exe -ExecutionPolicy Bypass -File deploy\local\prepare-env.ps1 `
  -PublicUrl https://YOUR-DEVICE.YOUR-TAILNET.ts.net
powershell.exe -ExecutionPolicy Bypass -File deploy\local\start.ps1
tailscale funnel --bg 8081
```

The backup command must run before the first production start because Laravel
automatically applies pending database migrations.

## Verify

```powershell
Invoke-RestMethod http://127.0.0.1:8081/up
Invoke-RestMethod https://YOUR-DEVICE.YOUR-TAILNET.ts.net/up
Invoke-RestMethod https://YOUR-DEVICE.YOUR-TAILNET.ts.net/api/public/psi
tailscale funnel status
```

Set the GitHub Actions repository variable to the Funnel origin, without
`/api`:

```text
VITE_API_BASE_URL=https://YOUR-DEVICE.YOUR-TAILNET.ts.net
```

Then run the `Deploy BRAVE public view to GitHub Pages` workflow.

## Stop

```powershell
tailscale funnel reset
powershell.exe -ExecutionPolicy Bypass -File deploy\local\stop.ps1
```

The computer, Docker Desktop, MySQL, and Tailscale must remain running and the
computer must not sleep while the public site is in use.
