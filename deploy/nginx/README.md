# nginx + php-fpm deployment

This fork runs ITFlow behind nginx and php-fpm instead of the upstream
Apache/mod_php image. The upstream image ignores `.htaccess` in its default
container and its mod_php environment made `Authorization` and
`X-Forwarded-For` handling awkward. The nginx stack passes those headers to
php-fpm directly.

## Topology

```
client
  |
  v
host nginx (TLS, public 443)
  |  proxy_pass http://127.0.0.1:8080
  v
container nginx (this image, port 8080)
  |  fastcgi_pass 127.0.0.1:9000
  v
php-fpm (user apache)
  |
  v
MariaDB (itflow-db)
```

The host nginx terminates TLS and is the only public listener. The container
nginx does not need certificates.

## Files

| File | Purpose |
| --- | --- |
| `Dockerfile` | Adds nginx and php-fpm to `itfloworg/itflow:latest`; keeps the base PHP 8.4 extensions and entrypoint. |
| `nginx.conf` | Container vhost, deny rules, FastCGI header contract. |
| `php-fpm.conf` | Pool user, worker limits, upload limits, session path, PATH. |
| `start-nginx.sh` | Starts php-fpm in the background and nginx in the foreground. |
| `docker-compose.example.yml` | Sanitized compose file for a new host. |

## Build and run

```sh
docker build -t itflow-nginx:20261019 deploy/nginx
```

Use `docker-compose.example.yml` as the starting point, set
`ITFLOW_DB_PASS`, and start it:

```sh
docker compose -f deploy/nginx/docker-compose.example.yml up -d
```

For the production layout, the compose file lives in `/opt/itflow` and uses:

```yaml
build:
  context: ./nginx
image: itflow-nginx:20261019
```

Rebuild after changing any file in this directory:

```sh
docker compose build itflow
docker compose up -d --no-deps itflow
```

## Host nginx

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $remote_addr;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Authorization $http_authorization;
    client_max_body_size 512M;
}
```

`Authorization` must be forwarded explicitly so `Authorization: Bearer ...`
API keys reach php-fpm. Do not rely on the default proxy header set.

## FastCGI contract

| Header | FastCGI param | Consumer |
| --- | --- | --- |
| `Authorization` | `HTTP_AUTHORIZATION` | `api/v1/validate_api_key.php` Bearer path |
| `X-Forwarded-For` | `HTTP_X_FORWARDED_FOR` | `getIP()` in `functions/request.php` |
| `X-Forwarded-Proto` | `HTTP_X_FORWARDED_PROTO` and `HTTPS` | HTTPS-only login and secure cookies |
| `X-Real-IP` | `HTTP_X_REAL_IP` | diagnostics |

`getIP()` reads `$_SERVER['HTTP_X_FORWARDED_FOR']`, not `getenv()`. `getenv()`
only sees the header under mod_php; under php-fpm it is a FastCGI param.

## Security parity with `.htaccess`

The container nginx mirrors the repo rules:

- `/config.php`, `/.git*`, `/db.sql`, `/.ht*` denied
- `/scripts/` and `/cron/` denied
- `/deploy/` denied
- no PHP execution under `/uploads/`; ordinary upload files are still served
- directory listing off

The app's root `.htaccess` carries the same `deploy` deny so an Apache rollback
does not expose these files.

## Operations

- Healthcheck: `wget -q --spider http://localhost:8080/login.php`. The listener
  includes `listen [::]:8080` so `localhost` does not fail on IPv6.
- Logs: `docker logs itflow` (nginx access/error and php-fpm worker output).
- Sessions: `/var/lib/php84/sessions`, owned by `apache`.
- Uploads: 512M in both nginx and php-fpm.
- `clear_env = yes` with `env[PATH]` set so `shellCommandsAvailable()` and
  shell-based maintenance continue to work.
- The base image's `entrypoint.sh` still handles config generation, ownership,
  and `crond`; only the final command changes to `start-nginx.sh`.

## Updates

The app updater runs `git fetch --all` and `git reset --hard origin/master`.
These deployment files are committed to this fork's `master`, so updates keep
them. Rebuild the image after pulling changes to this directory.

## Rollback to Apache

Keep a copy of the pre-nginx compose file. On the production host it is
`/opt/itflow/backups/audit-fix-20261019-203239/docker-compose.pre-nginx.yml`.

```sh
cp docker-compose.pre-nginx.yml docker-compose.yml
docker compose up -d --no-deps itflow
```

The upstream Apache image remains local. The app code is unchanged by the
rollback; `getIP()` works under both SAPIs.

## Caveats

- Apache packages remain in the base image because the inherited entrypoint
  references them. No Apache process starts. A minimal image can remove
  `apache2` and `php84-apache2` after trimming the entrypoint.
- Direct `/uploads/` retrieval is unchanged from the Apache deployment; it is
  a separate storage-authentication issue, not an HTTP-server issue.
- The container listener is plain HTTP on localhost. Do not publish port 8080
  on a public interface.