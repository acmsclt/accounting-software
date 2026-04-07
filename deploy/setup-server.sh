#!/bin/bash
##############################################################
# AccountingPro — One-Command Server Setup Script
# Run on your Ubuntu/Debian server as root:
#   bash deploy/setup-server.sh
##############################################################

set -e

APP_DIR="/var/www/html/system-ac"
PHP_VER="8.3"

echo ""
echo "╔══════════════════════════════════════╗"
echo "║  AccountingPro — Server Setup        ║"
echo "╚══════════════════════════════════════╝"
echo ""

# ── Detect server IP ──────────────────────────────────────────
SERVER_IP=$(hostname -I | awk '{print $1}')
echo "🌐 Server IP: $SERVER_IP"

# ── 1. Update packages ────────────────────────────────────────
echo ""
echo "📦 Updating packages..."
apt-get update -qq

# ── 2. Install Nginx ──────────────────────────────────────────
echo "🔧 Installing Nginx..."
apt-get install -y -qq nginx

# ── 3. Install PHP-FPM & extensions ──────────────────────────
echo "🐘 Installing PHP $PHP_VER..."
apt-get install -y -qq \
    php${PHP_VER}-fpm \
    php${PHP_VER}-mysql \
    php${PHP_VER}-mbstring \
    php${PHP_VER}-json \
    php${PHP_VER}-curl \
    php${PHP_VER}-openssl \
    php${PHP_VER}-xml \
    php${PHP_VER}-zip \
    php${PHP_VER}-gd \
    php${PHP_VER}-intl

# ── 4. Deploy Nginx config ────────────────────────────────────
echo "⚙️  Configuring Nginx..."
cat > /etc/nginx/sites-available/accountingpro << NGINXEOF
server {
    listen 80;
    listen [::]:80;
    server_name $SERVER_IP _;

    root ${APP_DIR}/public;
    index index.php index.html;
    client_max_body_size 50M;
    charset utf-8;

    access_log /var/log/nginx/accountingpro_access.log;
    error_log  /var/log/nginx/accountingpro_error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(env|git|htaccess) { deny all; return 404; }
    location ~ /database/             { deny all; return 404; }
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    gzip on;
    gzip_types text/plain text/css application/json application/javascript;
}
NGINXEOF

# Enable site, disable default
ln -sf /etc/nginx/sites-available/accountingpro /etc/nginx/sites-enabled/accountingpro
rm -f /etc/nginx/sites-enabled/default

# ── 5. Set file permissions ────────────────────────────────────
echo "🔒 Setting permissions..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR/public"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/logs" 2>/dev/null || true
chmod 600 "$APP_DIR/.env"

# ── 6. Test & reload Nginx ────────────────────────────────────
echo "✅ Testing Nginx config..."
nginx -t
systemctl reload nginx

# ── 7. PHP-FPM config tweaks ─────────────────────────────────
echo "⚙️  Tuning PHP-FPM..."
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 50M/' /etc/php/${PHP_VER}/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 50M/'            /etc/php/${PHP_VER}/fpm/php.ini
sed -i 's/memory_limit = .*/memory_limit = 256M/'             /etc/php/${PHP_VER}/fpm/php.ini
sed -i 's/max_execution_time = .*/max_execution_time = 120/'  /etc/php/${PHP_VER}/fpm/php.ini
systemctl restart php${PHP_VER}-fpm

echo ""
echo "╔══════════════════════════════════════╗"
echo "║   ✅ Server Setup Complete!           ║"
echo "╚══════════════════════════════════════╝"
echo ""
echo "🌐 App running at:  http://${SERVER_IP}/"
echo "🔑 Admin login:     admin@accountingpro.com / Admin@123"
echo ""
echo "📋 SSL Setup (optional):"
echo "   apt install certbot python3-certbot-nginx"
echo "   certbot --nginx -d your-domain.com"
echo ""
