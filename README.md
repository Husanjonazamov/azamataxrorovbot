# Telegram Konkurs Bot

Bu bot konkursda qatnashish uchun foydalanuvchilarni ro'yxatdan o'tkazish va boshqarish uchun yaratilgan.

## 🚀 Xususiyatlar

- ✅ Kanal obunasi tekshirish
- 📱 Telefon raqam orqali ro'yxatdan o'tish
- 🎫 Avtomatik tartib raqami berish
- 📊 Batafsil statistika
- 📤 Katta foydalanuvchilar bazasiga xabar yuborish
- 📋 CSV formatda ma'lumotlarni eksport qilish
- 🔧 Admin paneli

## 📋 O'rnatish

### 1. Fayllarni yuklab olish
```bash
git clone [repository-url]
cd telegram-konkurs-bot
```

### 2. Ma'lumotlar bazasi sozlash
`sql.php` faylida ma'lumotlar bazasi ma'lumotlarini o'zgartiring:
```php
$host = 'localhost';
$username = 'your_username';
$password = 'your_password';
$database = 'your_database';
```

### 3. Bot token sozlash
`config.php` faylida bot tokenini o'zgartiring:
```php
'token' => 'YOUR_BOT_TOKEN_HERE',
'admin_id' => 'YOUR_ADMIN_ID_HERE',
```

### 4. Webhook sozlash
```bash
curl -X POST "https://api.telegram.org/botYOUR_TOKEN/setWebhook" \
     -H "Content-Type: application/json" \
     -d '{"url": "https://yourdomain.com/bot.php"}'
```

## 🗄️ Ma'lumotlar bazasi jadvallari

### user_id
- `user_id` - Foydalanuvchi ID
- `step` - Foydalanuvchi holati
- `sana` - Ro'yxatdan o'tgan sana
- `temp_message` - Vaqtinchalik xabar

### users
- `chat_id` - Chat ID
- `phone_number` - Telefon raqam
- `username` - Username
- `first_name` - Ism
- `last_name` - Familiya
- `ticket_number` - Tartib raqami
- `sana` - Ro'yxatdan o'tgan sana

### kanallar
- `title` - Kanal nomi
- `link` - Kanal havolasi
- `channelID` - Kanal ID
- `type` - Kanal turi (lock/request/social)

### requests
- `user_id` - Foydalanuvchi ID
- `chat_id` - Kanal ID
- `sana` - So'rov vaqti

### sendusers
- `status` - Xabar yuborish holati
- `soni` - Yuborilgan xabarlar soni
- `send` - Jami yuborilishi kerak bo'lgan xabarlar

## 📱 Foydalanish

### Foydalanuvchilar uchun
1. `/start` - Botni boshlash
2. Kanallarga obuna bo'lish
3. Telefon raqamni yuborish
4. Tartib raqamini olish

### Admin uchun
1. `/panel` - Admin panelini ochish
2. `/fayl` - CSV faylini yuklab olish

## 🔧 Admin paneli funksiyalari

- **📫 Userga Xabar** - Barcha foydalanuvchilarga xabar yuborish
- **📊 Statistika** - Umumiy statistika
- **📈 Batafsil statistika** - Kunlik va haftalik statistika
- **📋 CSV yuklab olish** - Foydalanuvchilar ro'yxatini yuklab olish
- **🗒️ Xabar Xolati** - Xabar yuborish holatini ko'rish
- **🛑 Xabarni toʻxtatish** - Xabar yuborishni to'xtatish
- **🔒 Ommaviy kanal** - Majburiy obuna bo'lish uchun kanal qo'shish
- **📝 So'rov qabul qiluvchi** - So'rov orqali obuna bo'lish uchun kanal qo'shish
- **🔗 Ixtiyoriy havola** - Ixtiyoriy ijtimoiy tarmoq havolasi qo'shish
- **📺 Kanallar ro'yxati** - Barcha kanallarni ko'rish
- **🗑️ Kanal o'chirish** - Kanallarni o'chirish

## 🚀 Yaxshilangan funksiyalar

### Katta foydalanuvchilar bazasi uchun
- Batch processing (30 ta foydalanuvchi bir vaqtda)
- Rate limiting (50 xabar uchun 1 soniya kutish)
- Xatolarni qayd qilish
- Progress tracking

### Xavfsizlik
- SQL injection himoyasi
- XSS himoyasi
- Error logging
- Input validation

### Kod tuzilishi
- Modulli arxitektura
- Konfiguratsiya fayli
- Yordamchi funksiyalar
- Toza kod

## 📝 Log fayllari

- `bot_errors.log` - Xatolar log fayli
- `cron_errors.log` - Cron job xatolari
- `debug.log` - Debug ma'lumotlari

## ⏰ Cron Job

Bot xabar yuborish funksiyasi cron job orqali avtomatik ishlaydi:

### **Cron job sozlash:**
```bash
# Har daqika ishga tushirish
* * * * * /usr/bin/php /path/to/bot/cron_sender.php

# Har 5 daqiqa ishga tushirish
*/5 * * * * /usr/bin/php /path/to/bot/cron_sender.php
```

### **Cron job funksiyalari:**
- ✅ Avtomatik xabar yuborish
- 📊 Admin'ga hisobot yuborish
- 🔍 Xatolarni qayd qilish

Batafsil ma'lumot uchun `CRON_SETUP.md` faylini ko'ring.

## 🔧 Sozlash

### Kanal qo'shish
```sql
INSERT INTO kanallar (title, link, channelID, type) 
VALUES ('Kanal nomi', 'https://t.me/kanal', '@kanal', 'lock');
```

### Admin qo'shish
`config.php` faylida `admin_id` ni o'zgartiring.

## 📞 Yordam

Muammolar bo'lsa:
1. Log fayllarini tekshiring
2. Ma'lumotlar bazasi ulanishini tekshiring
3. Bot tokenini tekshiring
4. Webhook sozlamalarini tekshiring

## 📄 Litsenziya

Bu loyiha MIT litsenziyasi ostida tarqatiladi.
