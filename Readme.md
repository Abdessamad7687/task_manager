# Task Manager

A small Laravel 13 application using PHP 8.3+, MySQL, Blade, and Bootstrap 5.3. Bootstrap provides all frontend styling; there is no custom theme or frontend framework. SortableJS handles drag and drop, and Vite bundles the local assets (no runtime CDN).

## Features

- Create, edit, and delete tasks with a name, priority, project, and Laravel's `created_at` / `updated_at` timestamps.
- Drag a task by its handle to reorder it. Priorities save automatically as **1, 2, 3, …** from top to bottom.
- Move-up/down buttons provide keyboard-friendly reordering. Manual priority editing also works without JavaScript.
- Create projects and select one from the dropdown to see only its tasks.
- A fresh installation includes an empty **Inbox** project; it contains no sample tasks.
- Validation errors, delete confirmation, and visible save/error feedback.

Priority is scoped to a project. Inserting or editing a priority shifts other tasks; deleting a task closes the gap. Moving a task to another project renumbers both lists. A new task defaults to the end of its project. Timestamps display in UTC.

This is a shared task manager without authentication or individual accounts. Anyone with access can change tasks. Deploy on a trusted network or place authentication at the web server / access gateway before exposing it publicly.

## Requirements

- PHP **8.3 or newer**, including `pdo_mysql`, `mbstring`, `xml`/`dom`, `ctype`, `curl`, `fileinfo`, `openssl`, `session`, and `tokenizer`.
- Composer 2.
- MySQL **8.0+** with InnoDB (8.4 is used by the optional Docker configuration).
- Node.js **22.12+** or a current supported Node version and npm, to build frontend assets. Node is not required to serve built assets in production.
- Optional: Docker with Docker Compose to run MySQL locally.

`composer.json` declares PHP `^8.3` and resolves dependencies against PHP 8.3.0, so the committed lock file also installs on PHP 8.3.

## Local setup

Run these commands from the project directory:

```sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

Skip copying `.env` if you already configured it. Do not regenerate an existing production application key.

### 1. Start a MySQL database

Choose **one** option.

**Option A: Docker Compose**

With Docker running:

```sh
docker compose up -d --wait
```

The included `compose.yaml` runs only MySQL, bound to `127.0.0.1:3306`. The PHP app runs on your host. It reads `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_PORT` from `.env`; the example values work out of the box. Its named volume keeps data across restarts. Default passwords are for local development only.

If port 3306 is occupied, set `DB_PORT=3307` in `.env` before starting the container. Database initialization credentials only apply when the volume is first created; changing `.env` later does not change existing MySQL users/passwords.

**Option B: Native MySQL on macOS (Homebrew)**

```sh
brew install mysql@8.4
brew services start mysql@8.4
"$(brew --prefix mysql@8.4)/bin/mysql" -u root
```

Run the database/user creation SQL in Option C below, then set `.env` to match. MySQL 8.4 is keg-only, so use the full client path above or add its `bin` directory to your shell's PATH. Homebrew binds MySQL to localhost by default. See the [Homebrew MySQL 8.4 formula](https://formulae.brew.sh/formula/mysql%408.4).

Useful service commands:

```sh
brew services restart mysql@8.4
brew services stop mysql@8.4
```

This workspace is already configured with native MySQL, database `task_manager`, and an application-specific user. Its generated password is stored in the ignored local `.env` file. Preserve that file instead of copying `.env.example` over it. The former SQLite preview records were copied to MySQL; `database/preview-before-mysql.sqlite` is the retained backup.

**Option C: Existing MySQL installation**

Sign in with an administrative MySQL account and create a database and application user:

```sql
CREATE DATABASE task_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'task_manager'@'127.0.0.1' IDENTIFIED BY 'replace-with-your-password';
GRANT ALL PRIVILEGES ON task_manager.* TO 'task_manager'@'127.0.0.1';
```

Set `.env` to match your server and user:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=task_manager
DB_USERNAME=task_manager
DB_PASSWORD="replace-with-your-password"
```

For a remote database, use the actual database hostname and a MySQL user permitted to connect from the application server.

### 2. Migrate and run

```sh
php artisan config:clear
php artisan migrate
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000). Create a task in Inbox or create another project. No seeding, queue worker, or scheduler is needed.

For frontend development, run `npm run dev` in a second terminal instead of rebuilding after each asset change. Laravel serves the Blade pages; Vite serves only development assets. Stop Vite and run `npm run build` before production deployment.

### Troubleshooting

- **Connection refused:** start MySQL, check `DB_HOST`/`DB_PORT`, and, for Docker, run `docker compose ps` and `docker compose logs mysql`.
- **Access denied:** check credentials and the user's allowed MySQL host. An existing Docker volume retains its original credentials.
- **could not find driver:** enable PHP's `pdo_mysql` extension for both CLI PHP and PHP-FPM.
- **Missing Vite manifest / unstyled page:** run `npm ci` and `npm run build`; deploy the resulting `public/build` directory.
- **Session expired / HTTP 419:** reload the page. Ensure `storage` is writable and that the app URL/cookie configuration matches your host.
- **The task list changed:** another tab changed the same project. Reload before trying to reorder again.
- After `.env` changes, run `php artisan config:clear` locally, or rebuild the production configuration cache.

## Local MySQL administration (phpMyAdmin)

phpMyAdmin is installed separately from the Laravel app and runs at [http://127.0.0.1:8081](http://127.0.0.1:8081). Sign in with username `task_manager` and the `DB_PASSWORD` value in your local `.env`. This account manages only the application's database.

To install/start it on macOS:

```sh
brew install phpmyadmin
php -S 127.0.0.1:8081 -t "$(brew --prefix)/share/phpmyadmin"
```

Use PHP 8.3 with `mysqli` enabled. The configured MySQL host is `127.0.0.1`, port `3306`, with cookie-based login. The Homebrew configuration is `/opt/homebrew/etc/phpmyadmin.config.inc.php` on this machine; other installations use `$(brew --prefix)/etc/phpmyadmin.config.inc.php`. Keep the admin server bound to `127.0.0.1`. Stop the foreground server with Ctrl+C.

## Tests and checks

```sh
php artisan test --compact
vendor/bin/pint --format agent
npm run build
```

The feature tests use an isolated in-memory SQLite database by default; PHP's `pdo_sqlite` extension is required for that mode. They cover CRUD, timestamps, priority insertion/editing/deletion, ordering persistence, project filtering/moves, invalid orders, stale lists, and escaped task names.

To exercise the same tests on **MySQL**, create a separate database such as `task_manager_test`, grant the test user access, and run:

```sh
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=task_manager_test DB_USERNAME=task_manager DB_PASSWORD=your-test-password \
php artisan test --compact
```

Use **only a dedicated test database**. `RefreshDatabase` rebuilds tables, so never point tests at your development or production database. The suite exercises behavior; it is not a concurrent-load test.

Verification: all 13 feature tests (80 assertions) passed on PHP 8.3 against a dedicated MySQL 8.4.11 test database. Live MySQL migrations and the local application page were verified, and the preserved preview records were compared with the SQLite backup, including IDs, names, priorities, project associations, and timestamps. The production asset build also passed. The test database was isolated from the application database and removed after testing. Concurrent-load behavior has not been tested. The running local app uses MySQL; SQLite remains the default isolated test adapter.

## Production deployment (Nginx + PHP-FPM)

1. Provision PHP 8.3+ with PHP-FPM, Composer, Nginx, and a MySQL database. Use HTTPS for the application domain. Configure access protection if this shared app is reachable outside a trusted network.
2. Upload/clone the source into a directory such as `/var/www/task-manager`. Create `.env` with your actual domain, production database credentials, and:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://tasks.example.com
   SESSION_SECURE_COOKIE=true
   SESSION_DRIVER=file
   CACHE_STORE=file
   QUEUE_CONNECTION=sync
   ```

3. From the application directory, install and build:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   php artisan key:generate --force
   npm ci
   npm run build
   php artisan migrate --force
   php artisan optimize
   ```

   Run `key:generate` only on the **first** deployment. Keep the same `APP_KEY` on subsequent releases. You may build assets in CI and upload `public/build` instead of installing Node on the server. Include the lock files; use `install` / `ci` rather than updating dependencies during deployment.

4. Give the PHP-FPM user write access to `storage` and `bootstrap/cache`. Example for Ubuntu's `www-data` user:

   ```sh
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R ug+rwX storage bootstrap/cache
   ```

   Keep `.env` private and readable only by the deployment/PHP-FPM users. Do not use world-writable permissions.

5. Configure Nginx to serve **only `public/`**. The following server block goes inside your normal HTTPS configuration; adjust the domain, path, and PHP-FPM socket for your server:

   ```nginx
   server {
       listen 443 ssl;
       server_name tasks.example.com;
       root /var/www/task-manager/public;
       index index.php;

       ssl_certificate /etc/letsencrypt/live/tasks.example.com/fullchain.pem;
       ssl_certificate_key /etc/letsencrypt/live/tasks.example.com/privkey.pem;

       charset utf-8;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ ^/index\.php(/|$) {
           fastcgi_split_path_info ^(.+\.php)(/.+)$;
           fastcgi_pass unix:/run/php/php8.3-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
           fastcgi_hide_header X-Powered-By;
       }

       location ~ \.php$ {
           return 404;
       }

       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```

   Obtain the certificate first, add an HTTP-to-HTTPS redirect for port 80, then validate/reload Nginx (`sudo nginx -t`, `sudo systemctl reload nginx`). Never use the repository root as the web root. Restart/reload your PHP-FPM service after a release if opcode caching is enabled.

6. Visit the domain and verify create, edit, delete, project filtering, and drag-and-drop persistence after a page reload. `/up` is Laravel's application health endpoint; check a real task operation too, since `/up` does not verify database connectivity.

For subsequent releases, back up MySQL and preserve `.env`, `APP_KEY`, and `storage`. Install locked dependencies, deploy built assets, run `php artisan migrate --force`, rebuild caches with `php artisan optimize`, and reload PHP-FPM. No background workers are required.

## Implementation

- `app/Services/TaskOrdering.php`: transactional ordering and project-level locks. All task mutations lock the project row, including when its list is empty. Cross-project moves lock parents in ID order.
- `app/Http/Controllers/`: task and project request handling.
- `app/Http/Requests/SaveTaskRequest.php`: task validation.
- `database/migrations/`: `projects` and `tasks` tables, a project foreign key, and an index on `(project_id, priority)`.
- `resources/views/`: Bootstrap Blade views.
- `resources/js/app.js`: drag/drop, keyboard reordering, confirmations, and save/error feedback.

A reorder request must contain each task in the selected project exactly once, plus the version rendered with the list. Invalid or stale requests leave the database unchanged. On an uncertain network failure, the browser restores its previous display and requires a reload before another reorder.

Laravel Boost is a development-only dependency added by the Laravel starter's agent setup; it is not installed by `composer install --no-dev`.

References: [Laravel installation](https://laravel.com/framework/docs), [Laravel deployment](https://laravel.com/framework/docs/deployment), [Bootstrap documentation](https://getbootstrap.com/docs/5.3/).
