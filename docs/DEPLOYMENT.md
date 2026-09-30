# Deploying to Railway

Andraws · Sprint 5

I can't click through the Railway dashboard for you - I don't have your account. What I can do is get the app ready and write out exactly what to click. Checked this against Railway's own current docs rather than guessing, since getting a variable name wrong here would just silently break the database connection.

The good news: Railway auto-detects Laravel and runs it for you (via php-fpm and Caddy) - no Dockerfile needed.

## 1. Create the project and connect the repo
1. [railway.com/new](https://railway.com/new) → **Deploy from GitHub repo**
2. Pick this repo. Railway will detect it's a Laravel app automatically.
3. It'll try to deploy right away and fail - that's expected, there's no database or environment variables yet. Keep going.

## 2. Add a MySQL database
1. On the project canvas, click **+ New** → **Database** → **Add MySQL**
2. That's it - Railway runs it for you and exposes its connection details as variables the app service can reference.

## 3. Set the app service's environment variables
Go to your app service (not the MySQL one) → **Variables** → **Raw Editor**, and paste these in:

```
APP_KEY=                         # run `php artisan key:generate --show` locally, paste the output here
APP_ENV=production
APP_DEBUG=false
APP_URL=                         # fill in after step 4, once you have the Railway domain

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

LOG_CHANNEL=stderr
LOG_STDERR_FORMATTER=\Monolog\Formatter\JsonFormatter

FRONTEND_URL=                    # leave blank for now - this is Task 3, once Minh's Netlify URL exists
```

The `${{MySQL.MYSQLHOST}}` syntax tells Railway to pull that value from the MySQL service automatically - don't type in real values there, that exact text is what goes in the box.

`LOG_CHANNEL=stderr` matters here specifically: Railway's disk is temporary, so without this, error logs write to a file that gets wiped on every redeploy, and you'd never actually see what went wrong.

## 4. Set the pre-deploy command
This runs migrations and caches config automatically, every time you deploy - you never need to SSH in and run `php artisan migrate` by hand.

1. App service → **Settings** → **Deploy**
2. Under **Pre-Deploy Command**, paste:
   ```
   chmod +x ./railway/init-app.sh && sh ./railway/init-app.sh
   ```
   (the `chmod +x` is there because uploading through GitHub's web interface doesn't always preserve the file's executable permission - this line fixes that automatically on every deploy, so it's not something you need to remember to do yourself)
3. Save, then go to **Deployments** and trigger a redeploy.

## 5. Get a public URL
1. App service → **Settings** → **Networking** → **Generate Domain**
2. Copy that URL, go back to step 3's variables, and fill in `APP_URL` with it
3. Redeploy once more so the new `APP_URL` takes effect

## 6. Check it worked
Open `<your-railway-url>/api/reflections` in the browser. You should get a `401 Unauthenticated` JSON response, not a blank error page or a 500. A 401 here is actually correct - it means the server, the database connection, and auth are all working; you're just not logged in.

## Redeploying later
Railway redeploys automatically on every push to the connected branch. The pre-deploy command from step 4 reruns every time, so migrations and config stay current without you doing anything extra.

## A note on file storage (evidence uploads)
By default, uploaded evidence files save to the app's own local disk. Railway's filesystem resets on every redeploy, which would silently delete any previously uploaded evidence. Fine to leave as-is for a capstone demo, but if evidence needs to survive long-term, look at a [Railway volume](https://docs.railway.com/volumes) or switching `FILESYSTEM_DISK` to S3 - that's a bigger change, worth its own ticket rather than folding into this one.

## Task 3 - CORS (do this once Minh has the Netlify URL)
Once the frontend's real Netlify domain exists, set `FRONTEND_URL` in the variables above to that exact URL (e.g. `https://reflection-diary.netlify.app` - no trailing slash) and redeploy. `config/cors.php` already reads this automatically; nothing else needs to change. See `docs/API.md` for how the CORS config works.
