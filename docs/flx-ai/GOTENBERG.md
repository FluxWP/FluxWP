# Gotenberg kurulumu (şifreli, HTTPS)

Flux AI görselleri WhatsApp'a ve e-postaya PNG olarak göndermek için Gotenberg kullanır. Site Hostinger'da, Gotenberg ise başka bir sunucuda çalıştığı için bağlantı internetten geçer. Bu yüzden kullanıcı adı ve şifre (Gotenberg 8'in kendi basic auth'u) ile HTTPS (Caddy) birlikte kullanılır. 3000 portu dışarıya kapalı kalır.

## 1. DNS
Hostinger › Alan adları › DNS bölümünde bir A kaydı ekleyin: `gotenberg` → Gotenberg sunucusunun IP adresi. Sonuç `gotenberg.example.org` olur.

## 2. Sunucuda klasör ve şifre
```bash
mkdir -p ~/gotenberg && cd ~/gotenberg
echo "GOTENBERG_API_BASIC_AUTH_USERNAME=flx" > .env
echo "GOTENBERG_API_BASIC_AUTH_PASSWORD=$(openssl rand -hex 24)" >> .env
chmod 600 .env
cat .env   # şifreyi buradan kopyalayın; chat'e yazmayın
```

## 3. docker-compose.yml
```yaml
services:
  gotenberg:
    image: gotenberg/gotenberg:8
    restart: unless-stopped
    command: ["gotenberg", "--api-enable-basic-auth"]
    env_file: .env
    # ports YOK: 3000 dışarıya açılmaz, yalnız Caddy erişir

  caddy:
    image: caddy:2
    restart: unless-stopped
    ports: ["80:80", "443:443"]
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
    depends_on: [gotenberg]

volumes:
  caddy_data:
```

## 4. Caddyfile
```
gotenberg.example.org {
	reverse_proxy gotenberg:3000
}
```
Caddy, Let's Encrypt sertifikasını kendisi alır ve yeniler.

## 5. Çalıştır
```bash
docker rm -f $(docker ps -q --filter ancestor=gotenberg/gotenberg:8) 2>/dev/null   # eski "docker run -p 3000:3000" kurulumu varsa kapatır
docker compose pull && docker compose up -d
curl -s -o /dev/null -w '%{http_code}\n' https://gotenberg.example.org/health   # 200 beklenir
curl -s -o /dev/null -w '%{http_code}\n' https://gotenberg.example.org/        # 401 beklenir: şifre çalışıyor
```

**Sunucuda 80/443 başka bir şey tarafından kullanılıyorsa** (Evolution için nginx, Traefik ya da Caddy kuruluysa), compose dosyasından `caddy` servisini çıkarın. Gotenberg'e `ports: ["127.0.0.1:3000:3000"]` verin ve mevcut proxy'ye `gotenberg.example.org → 127.0.0.1:3000` kuralını ekleyin.

## 6. Siteye bağla
Flux AI › Ayarlar › **Görseller**:
- Sunucuda görsel çevirici: `https://gotenberg.example.org`
- Gotenberg kullanıcı / şifre: `flx` ve `.env` dosyasındaki şifre
- **Test et** (0.2.1): ✓ ve küçük bir önizleme görünmeli. "Kullanıcı adı veya şifre kabul edilmedi" çıkarsa şifreyi tekrar yapıştırın.
- **Kaydet**. Şifre kaydedildikten sonra yalnız son 4 hanesi görünür.

Notlar:
- Basic auth için güncel `gotenberg:8` imajı gerekir. `docker compose pull` bunu sağlar.
- `/health` adresi şifresizdir, yalnız durum bilgisi verir.
- Gotenberg yoksa ya da ulaşılamıyorsa WhatsApp'a görsel yerine metin gider.
