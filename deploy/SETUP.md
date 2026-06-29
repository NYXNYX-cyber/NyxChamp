# Setup panduan singkat untuk deploy stack NyxChamp.

## Prasyarat

- Docker 24+ + Docker Compose v2
- PHP 8.3+ dengan extensions: pdo_mysql, redis (atau phpredis)
- Composer 2
- Node 20+ (untuk build frontend)

## 1. Start MySQL + Redis stack

```bash
cd /opt/nyxchamp-stack
cp .env.example .env
# Edit .env: set MYSQL_ROOT_PASSWORD, MYSQL_PASSWORD kuat!
docker compose up -d
docker compose ps  # verify healthy
```

## 2. Setup Laravel .env

```bash
cd /var/www/nyxchamp
cp .env.example .env
# Edit:
#   DB_CONNECTION=mysql
#   DB_HOST=<ip-stack>
#   DB_PORT=3306
#   DB_DATABASE=nyxchamp
#   DB_USERNAME=nyxchamp
#   DB_PASSWORD=<dari .env stack>
#   QUEUE_CONNECTION=redis
#   CACHE_STORE=redis
#   SESSION_DRIVER=redis
#   REDIS_HOST=<ip-stack>
#   REDIS_PORT=6380
#   REDIS_DB=0
#   REDIS_CACHE_DB=1
php artisan key:generate
```

## 3. Install dependencies + migrate + seed

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan db:seed --force  # 8 lomba contoh + 3 user dev
php artisan storage:link
```

## 4. Setup systemd worker

```bash
sudo cp deploy/systemd/nyxchamp-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now nyxchamp-queue
sudo systemctl status nyxchamp-queue
sudo journalctl -u nyxchamp-queue -f  # tail logs
```

Untuk Reverb (Fase 7):
```bash
sudo cp deploy/systemd/nyxchamp-reverb.service /etc/systemd/system/
sudo systemctl enable --now nyxchamp-reverb
```

## 5. Setup cron untuk scheduler

Tambah ke crontab root:
```
* * * * * cd /var/www/nyxchamp && php artisan schedule:run >> /dev/null 2>&1
```

Ini trigger `scrape` setiap Senin 05:00 + Jumat 15:00 WIB (lihat
`routes/console.php`).

## 6. Setup firecrawl-keeper SSH access

Scraper Python (di host Laravel atau host terpisah) perlu SSH ke
Firecrawl host (10.10.1.28) untuk auto-start stack.

Opsi A — password (dev only):
```bash
# Di .env Laravel:
FIRECRAWL_SSH_HOST=10.10.1.28
FIRECRAWL_SSH_USER=root
FIRECRAWL_SSH_PASSWORD=<password>
```

Opsi B — SSH key (production):
```bash
# Di host Laravel:
ssh-keygen -t ed25519 -f /root/.ssh/nyxchamp_firecrawl -N ""
ssh-copy-id -i /root/.ssh/nyxchamp_firecrawl.pub root@10.10.1.28

# Di .env Laravel:
FIRECRAWL_SSH_HOST=10.10.1.28
FIRECRAWL_SSH_USER=root
FIRECRAWL_SSH_KEY_PATH=/root/.ssh/nyxchamp_firecrawl
```

## 7. (Opsional) Setup nginx

Lihat `deploy/nginx/nyxchamp.conf` (akan ditambah di Fase 7+).

## Verifikasi

```bash
# Queue worker hidup
systemctl status nyxchamp-queue
journalctl -u nyxchamp-queue -n 50

# MySQL + Redis reachable
php artisan tinker --execute="echo DB::selectOne('SELECT VERSION() as v')->v; echo PHP_EOL; echo Redis::ping();"

# Scheduler terdaftar
php artisan schedule:list
```

## Catatan operasional

- **Backup MySQL harian**:
  ```bash
  docker exec nyxchamp-mysql mysqldump --all-databases -uroot -p"$MYSQL_ROOT_PASSWORD" | gzip > /backup/nyxchamp-$(date +%F).sql.gz
  ```

- **Rotate logs** Laravel: `logrotate` sudah handle `storage/logs/*.log`.

- **Update stack**:
  ```bash
  cd /opt/nyxchamp-stack
  docker compose pull
  docker compose up -d
  ```

- **Reset total** (HATI-HATI):
  ```bash
  cd /opt/nyxchamp-stack
  docker compose down -v   # hapus volume = hapus semua data
  docker compose up -d
  php artisan migrate:fresh --seed --force
  ```
