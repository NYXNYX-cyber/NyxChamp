#!/bin/bash
# Start script untuk NyxChamp dev server (semua service)
# Jalankan: bash ./start-services.sh

DIR="/home/nyx/Documents/NyxChamp"
cd "$DIR"

echo "=== NyxChamp Start Services ==="

echo "[1/4] Laravel HTTP Server (:8000)..."
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &
PID1=$!
disown

echo "[2/4] Reverb WebSocket (:8080)..."
nohup php artisan reverb:start --port=8080 > storage/logs/reverb.log 2>&1 &
PID2=$!
disown

echo "[3/4] Queue Worker (scraping)..."
nohup php artisan queue:work --queue=scraping --tries=3 --backoff=60,300,900 > storage/logs/queue.log 2>&1 &
PID3=$!
disown

echo "[4/4] FastAPI Scraper (:8001)..."
cd scraper
nohup .venv/bin/uvicorn app.main:app --host=0.0.0.0 --port=8001 > ../storage/logs/scraper.log 2>&1 &
PID4=$!
disown
cd "$DIR"

echo ""
echo "PID: $PID1 $PID2 $PID3 $PID4"
echo "Done! Cek: ps aux | grep artisan"

echo "$PID1 $PID2 $PID3 $PID4" > /tmp/nyxchamp-pids.txt
