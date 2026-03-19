#!/usr/bin/env bash
set -e

# ── PHP binary ───────────────────────────────────────────────────────────────
PHP=""
for cmd in php8.3 php8.2 php8.1 php8.0 php; do
  command -v "$cmd" &>/dev/null && PHP="$cmd" && break
done
if [ -z "$PHP" ]; then
  echo "PHP bulunamadı."
  echo "Termux: pkg install php"
  echo "Ubuntu: sudo apt-get install php8.3"
  exit 1
fi

PORT="${1:-8080}"
LOCAL_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
[ -z "$LOCAL_IP" ] && LOCAL_IP="localhost"

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║          LocalTalk  –  Lokal Çeviri Uygulaması       ║"
echo "╠══════════════════════════════════════════════════════╣"
printf  "║  Adres  ▸  http://%-34s║\n" "${LOCAL_IP}:${PORT}"
printf  "║  Ayarlar▸  http://%-34s║\n" "${LOCAL_IP}:${PORT}/settings.php"
echo    "╠══════════════════════════════════════════════════════╣"
echo    "║  iOS/HTTPS için Cloudflare Tunnel:                   ║"
echo    "║    cloudflared tunnel --url http://localhost:${PORT}  ║"
echo    "╚══════════════════════════════════════════════════════╝"
echo ""

cd "$(dirname "$0")"

"$PHP" \
  -d upload_max_filesize=50M \
  -d post_max_size=50M \
  -d memory_limit=128M \
  -S "0.0.0.0:${PORT}"
