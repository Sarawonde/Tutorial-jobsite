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

## Deploy to Vercel

The included `vercel.json` runs the application with the community PHP runtime.
Production requires a persistent PostgreSQL database (Neon works well): add its
pooled connection string as the `DATABASE_URL` environment variable in Vercel.
The application creates its tables automatically and stores sessions in Postgres.
Local development continues to use SQLite.

To designate the production administrator without direct database access, set
`ADMIN_EMAIL` in Vercel to an existing registered email and redeploy. If the
account does not exist yet, also set `ADMIN_PASSWORD` to a password of at least
8 characters; `ADMIN_NAME` is optional.

## Local development

With PHP and the PDO SQLite extension installed:

```sh
php -S localhost:8000
```

Then open `http://localhost:8000`.
