#!/bin/bash

# Request Admin System Deployment Script
# Bu script projeyi production ortamına deploy eder

set -e

echo "🚀 Request Admin System Deployment başlatılıyor..."

# Renkli output için
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Proje dizini
PROJECT_DIR="/var/www/requestadmin"
BACKUP_DIR="/var/www/backups/requestadmin"

# Backup oluştur
echo -e "${YELLOW}📦 Mevcut versiyon yedekleniyor...${NC}"
if [ -d "$PROJECT_DIR" ]; then
    mkdir -p "$BACKUP_DIR"
    BACKUP_NAME="backup-$(date +%Y%m%d-%H%M%S)"
    cp -r "$PROJECT_DIR" "$BACKUP_DIR/$BACKUP_NAME"
    echo -e "${GREEN}✅ Backup oluşturuldu: $BACKUP_DIR/$BACKUP_NAME${NC}"
fi

# Dependencies yükle
echo -e "${YELLOW}📦 Dependencies yükleniyor...${NC}"
cd "$PROJECT_DIR"
npm install --production

# Veritabanı migration'ları çalıştır
echo -e "${YELLOW}🗄️ Veritabanı migration'ları çalıştırılıyor...${NC}"
mysql -u ittoolbox -p requestadmin < database/schema.sql

# Frontend build
echo -e "${YELLOW}🏗️ Frontend build ediliyor...${NC}"
npm run build

# Uploads dizini oluştur
echo -e "${YELLOW}📁 Uploads dizini oluşturuluyor...${NC}"
mkdir -p uploads
chmod 755 uploads

# PM2 ile restart
echo -e "${YELLOW}🔄 PM2 ile restart ediliyor...${NC}"
pm2 delete requestadmin-api 2>/dev/null || true
pm2 start ecosystem.config.js --env production

# Nginx konfigürasyonunu kopyala
echo -e "${YELLOW}🌐 Nginx konfigürasyonu güncelleniyor...${NC}"
cp nginx/requestadmin.devkit.com.tr.conf /etc/nginx/sites-available/
ln -sf /etc/nginx/sites-available/requestadmin.devkit.com.tr.conf /etc/nginx/sites-enabled/

# Nginx test ve reload
nginx -t && systemctl reload nginx

# SSL sertifikası kontrolü
echo -e "${YELLOW}🔒 SSL sertifikası kontrol ediliyor...${NC}"
if [ ! -f "/etc/letsencrypt/live/requestadmin.devkit.com.tr/fullchain.pem" ]; then
    echo -e "${RED}⚠️ SSL sertifikası bulunamadı. Certbot ile oluşturun:${NC}"
    echo "certbot --nginx -d requestadmin.devkit.com.tr"
fi

# Health check
echo -e "${YELLOW}🏥 Health check yapılıyor...${NC}"
sleep 5
if curl -f http://localhost:3001/api/health > /dev/null 2>&1; then
    echo -e "${GREEN}✅ API health check başarılı${NC}"
else
    echo -e "${RED}❌ API health check başarısız${NC}"
    exit 1
fi

# PM2 status
echo -e "${YELLOW}📊 PM2 durumu:${NC}"
pm2 status

echo -e "${GREEN}🎉 Deployment başarıyla tamamlandı!${NC}"
echo -e "${GREEN}🌐 Site: https://requestadmin.devkit.com.tr${NC}"
echo -e "${GREEN}📊 PM2 Logs: pm2 logs requestadmin-api${NC}"
echo -e "${GREEN}🔄 PM2 Restart: pm2 restart requestadmin-api${NC}"


