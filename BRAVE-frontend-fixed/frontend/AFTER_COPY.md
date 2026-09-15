# BRAVE frontend — after copying

The production frontend is already compiled into the repaired backend's
`public` folder.

For frontend development, use Node.js 20.19 or newer and run:

```powershell
npm install
npm run dev
```

The frontend reads its backend URL and MapTiler browser key from `.env` and
`.env.local`. The expected variable names are documented in `.env.example`.
