# Request Admin System - React + Node.js

Modern İdari İşler Talep Yönetim Sistemi - React, TypeScript ve Supabase ile geliştirilmiştir.

## Özellikler

- ✅ Modern React + TypeScript arayüzü
- ✅ Supabase veritabanı entegrasyonu
- ✅ 3 farklı kullanıcı rolü (Admin, İdari İşler, Çalışan)
- ✅ Responsive tasarım
- ✅ Real-time güncellemeler
- ✅ Güvenli kimlik doğrulama
- ✅ Dosya yükleme desteği
- ✅ Lokasyon bazlı yetkilendirme

## Kurulum

1. **Bağımlılıkları yükleyin:**
   ```bash
   npm install
   ```

2. **Supabase projesini kurun:**
   - [Supabase](https://supabase.com) hesabı oluşturun
   - Yeni proje oluşturun
   - "Connect to Supabase" butonuna tıklayın

3. **Veritabanı şemasını oluşturun:**
   - Supabase SQL Editor'da migration dosyalarını çalıştırın

4. **Uygulamayı başlatın:**
   ```bash
   npm run dev
   ```

## Kullanım

### Roller ve Yetkiler

**Admin:**
- Tüm sistem yönetimi
- Firma onayları
- Kullanıcı yönetimi
- Sistem ayarları

**İdari İşler (HR):**
- Talep yönetimi
- Çalışan yönetimi
- Raporlama
- Lokasyon bazlı yetkilendirme

**Çalışan:**
- Talep oluşturma
- Talep takibi
- Profil yönetimi

### Test Kullanıcıları

Sistem kurulumundan sonra aşağıdaki test kullanıcıları oluşturulacaktır:

- **Admin:** admin@system.com
- **İdari İşler:** hr@test.com  
- **Çalışan:** employee@test.com

## Teknolojiler

- **Frontend:** React 18, TypeScript, Tailwind CSS
- **Backend:** Supabase (PostgreSQL)
- **Kimlik Doğrulama:** Supabase Auth
- **State Management:** React Query
- **Form Yönetimi:** React Hook Form + Zod
- **UI Components:** Lucide React Icons

## Geliştirme

```bash
# Geliştirme sunucusunu başlat
npm run dev

# Production build
npm run build

# Type checking
npm run type-check
```

## Lisans

MIT License