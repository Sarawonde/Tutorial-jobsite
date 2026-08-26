# TutorLink

TutorLink is a PHP application backed by SQLite.

## Deploy to Railway

1. Push this repository to GitHub.
2. In Railway, create a project and choose **Deploy from GitHub repo**.
3. Add a Railway volume mounted at `/data`. This is required so accounts, jobs,
   messages, and login sessions survive deployments and restarts.
4. Deploy, then create a public domain under the service's Networking settings.

The included `Dockerfile` and `railway.json` configure the web service. The app
automatically creates and seeds its SQLite database in `/data` on first launch.

## Vercel

Vercel does not provide a native PHP runtime, so this monolithic PHP application
cannot be deployed there directly. Host the application on Railway. If a Vercel
domain is required, it can be configured as a reverse proxy to the Railway URL,
but that adds an unnecessary second deployment and another point of failure.

## Local development

With PHP and the PDO SQLite extension installed:

```sh
php -S localhost:8000
```

Then open `http://localhost:8000`.
