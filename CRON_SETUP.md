# Cron Job Sozlash Qo'llanmasi

Bu qo'llanma Telegram bot uchun cron job sozlash bo'yicha ma'lumot beradi.

## 🚀 Cron Job nima?

Cron job - bu Linux/Unix tizimlarida vaqt bo'yicha avtomatik ishga tushiriladigan dasturlar. Bizning holatda u xabarlarni avtomatik yuborish uchun ishlatiladi.

## 📋 Cron Job turlari

### 1. **Avtomatik xabar yuborish**
- `sendusers` jadvalida `status = 'active'` bo'lgan xabarlarni yuboradi
- Har daqiqa tekshiriladi va xabar yuboriladi
- Admin'ga hisobot yuboradi

## ⚙️ Cron Job sozlash

### 1. **Crontab ochish**
```bash
crontab -e
```

### 2. **Cron job qo'shish**

#### Har daqiqa ishga tushirish:
```bash
* * * * * /usr/bin/php /path/to/your/bot/cron_sender.php
```

#### Har 5 daqiqa ishga tushirish:
```bash
*/5 * * * * /usr/bin/php /path/to/your/bot/cron_sender.php
```

#### Har soat ishga tushirish:
```bash
0 * * * * /usr/bin/php /path/to/your/bot/cron_sender.php
```

#### Har kun soat 9:00 da ishga tushirish:
```bash
0 9 * * * /usr/bin/php /path/to/your/bot/cron_sender.php
```

### 3. **Cron format tushuntirilishi**
```
* * * * * command
│ │ │ │ │
│ │ │ │ └── Hafta kuni (0-7, 0 = yakshanba)
│ │ │ └──── Oy (1-12)
│ │ └────── Kun (1-31)
│ └──────── Soat (0-23)
└────────── Daqiqa (0-59)
```

## 🔧 Cron Job sozlash misollari

### **Har daqiqa ishga tushirish:**
```bash
* * * * * /usr/bin/php /home/user/public_html/bot/cron_sender.php
```

### **Har 5 daqiqa ishga tushirish:**
```bash
*/5 * * * * /usr/bin/php /home/user/public_html/bot/cron_sender.php
```

### **Har soat ishga tushirish:**
```bash
0 * * * * /usr/bin/php /home/user/public_html/bot/cron_sender.php
```

### **Kuniga 2 marta (9:00 va 18:00):**
```bash
0 9,18 * * * /usr/bin/php /home/user/public_html/bot/cron_sender.php
```

### **Faqat ish kunlari soat 10:00 da:**
```bash
0 10 * * 1-5 /usr/bin/php /home/user/public_html/bot/cron_sender.php
```

## 📁 Fayl yo'llari

### **Linux/Unix:**
```bash
/usr/bin/php /home/username/public_html/bot/cron_sender.php
```

### **Windows (Task Scheduler):**
```cmd
C:\xampp\php\php.exe C:\xampp\htdocs\bot\cron_sender.php
```

### **cPanel:**
```bash
/usr/local/bin/php /home/username/public_html/bot/cron_sender.php
```

## 🔍 Cron Job tekshirish

### 1. **Cron job ro'yxatini ko'rish:**
```bash
crontab -l
```

### 2. **Cron log fayllarini tekshirish:**
```bash
tail -f /var/log/cron
```

### 3. **Bot log fayllarini tekshirish:**
```bash
tail -f /path/to/bot/cron_errors.log
```

## ⚠️ Muhim eslatmalar

### **1. PHP yo'li**
PHP to'g'ri yo'lda ekanligini tekshiring:
```bash
which php
```

### **2. Fayl huquqlari**
Cron faylini o'qish huquqi borligini tekshiring:
```bash
chmod 644 cron_sender.php
```

### **3. Ma'lumotlar bazasi ulanishi**
Cron job ma'lumotlar bazasiga ulana olayotganini tekshiring.

### **4. Xatolarni qayd qilish**
Cron job xatolarini ko'rish uchun:
```bash
* * * * * /usr/bin/php /path/to/bot/cron_sender.php >> /path/to/bot/cron.log 2>&1
```

## 🛠️ Muammolarni hal qilish

### **Cron job ishlamayapti:**
1. PHP yo'li to'g'ri ekanligini tekshiring
2. Fayl yo'li to'g'ri ekanligini tekshiring
3. Fayl huquqlarini tekshiring
4. Log fayllarini tekshiring

### **Xabar yuborilmayapti:**
1. `sendusers` jadvalida `status = 'active'` borligini tekshiring
2. Bot tokenini tekshiring
3. Ma'lumotlar bazasi ulanishini tekshiring



## 📞 Yordam

Muammolar bo'lsa:
1. Log fayllarini tekshiring
2. Cron job ro'yxatini tekshiring
3. PHP va fayl yo'llarini tekshiring
4. Server vaqtini tekshiring

## 🔒 Xavfsizlik

- Cron faylini public papkada saqlamang
- Fayl huquqlarini to'g'ri sozlang
- Log fayllarini muntazam tozalang
- Xatolarni kuzatib boring
