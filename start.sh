#!/usr/bin/env bash
set -e

# Find PHP binary
PHP=""
for cmd in php8.3 php8.2 php8.1 php8.0 php; do
  if command -v "$cmd" &>/dev/null; then
    PHP="$cmd"
    break
  fi
done

if [ -z "$PHP" ]; then
  echo "PHP bulunamadı. Kurulum: sudo apt-get install php"
  exit 1
fi

PORT="${1:-8080}"
HOST="0.0.0.0"

# Get local IP
LOCAL_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
if [ -z "$LOCAL_IP" ]; then
  LOCAL_IP="localhost"
fi

echo "╔══════════════════════════════════════╗"
echo "║        LocalTalk Çeviri Uygulaması        ║"
echo "╠══════════════════════════════════════╣"
echo "║  Yerel adres : http://localhost:$PORT"
echo "║  Ağ adresi   : http://$LOCAL_IP:$PORT"
echo "║  Paylaşım URL: http://$LOCAL_IP:$PORT/index.php"
echo "╠══════════════════════════════════════╣"
echo "║  Durdurmak için Ctrl+C                ║"
echo "╚══════════════════════════════════════╝"
echo ""
echo "İlk kullanımda Ayarlar sayfasından Groq API anahtarınızı girin:"
echo "  http://localhost:$PORT/settings.php"
echo ""

cd "$(dirname "$0")"
"$PHP" \
  -d upload_max_filesize=50M \
  -d post_max_size=50M \
  -d memory_limit=128M \
  -S "$HOST:$PORT"
