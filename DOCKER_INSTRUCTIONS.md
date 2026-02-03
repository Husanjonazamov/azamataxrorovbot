# Docker Orqali Ishga Tushirish Qo'llanmasi

Ushbu loyiha Docker yordamida oson ishga tushirish uchun moslashtirilgan. Quyidagi qadamlarni bajaring.

## 1. Tayyorgarlik

Avval `sql.php` va `config.php` fayllarini yarating.

**`config.php`:**
```php
<?php
$token = 'SIZNING_BOT_TOKENINGIZ'; // BotFather'dan olingan token
$admin = 'SIZNING_ADMIN_ID';       // O'zingizning Telegram ID raqamingiz
?>
```

**`sql.php`:**
`sql.example.php` faylidan nusxa oling:
```bash
cp sql.example.php sql.php
```

## 2. Dockerni Ishga Tushirish

Terminalda loyiha papkasida turing va quyidagi buyruqni bering:

```bash
docker-compose up -d --build
```
Bu buyruq bot va ma'lumotlar bazasini (MySQL) ishga tushiradi.

## 3. Webhookni Sozlash

Docker orqali ishga tushganda, bot **8080** portida ishlaydi (yoki `APP_PORT` orqali o'zgartirishingiz mumkin).

### Agar serverda domen bo'lsa (tavsiya etiladi):
Serveringizda Nginx yoki Apache orqali 8080 portni domenga (masalan, `https://bot.domain.uz`) yo'naltirishingiz kerak.
Keyin webhookni quyidagicha sozlaysiz:

```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
     -H "Content-Type: application/json" \
     -d '{"url": "https://bot.domain.uz/bot.php"}'
```

### Agar lokal kompyuterda yoki to'g'ridan-to'g'ri IP orqali (test uchun):
Agar sizda statik IP bo'lsa va 8080 port ochiq bo'lsa:

```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
     -H "Content-Type: application/json" \
     -d '{"url": "http://SIZNING_IP_MANZILINGIZ:8080/bot.php"}'
```
*Eslatma: Telegram Webhook uchun HTTPS talab qiladi, shuning uchun ishlab chiqarishda (production) albatta domen va SSL sertifikatidan foydalaning (Cloudflare yoki Let's Encrypt).*

## 4. Ma'lumotlar Bazasini Tekshirish

Docker ichidagi MySQL bazasiga kirish uchun:
```bash
docker-compose exec db mysql -u bot_user -pbot_password bot_db
```
Bu yerda `users`, `user_id` va boshqa jadvallarni ko'rishingiz mumkin.

## 5. To'xtatish

Botni to'xtatish uchun:
```bash
docker-compose down
```
