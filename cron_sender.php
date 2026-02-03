<?php
// Cron job uchun xabar yuborish fayli
// Bu faylni cron orqali ishga tushirish uchun yaratildi

// Konfiguratsiya faylini yuklash
require_once("config.php");
require_once("sql.php");

// Yordamchi funksiyalar
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, 'cron_errors.log');
}

function bot($method, $datas = []) {
    global $token;
    $url = "https://api.telegram.org/bot$token/$method";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    
    if(curl_error($ch)) {
        logError("CURL Error: " . curl_error($ch));
        return false;
    }
    
    curl_close($ch);
    return json_decode($res);
}

// Yaxshilangan send funksiyasi - katta foydalanuvchilar bazasi uchun
function sendToAllUsers($message, $type = 'text', $file_path = null, $caption = null) {
    global $connect;
    
    // Foydalanuvchilarni batched qilib olish
    $batch_size = 30; // Telegram API limiti
    $offset = 0;
    $success_count = 0;
    $error_count = 0;
    
    while(true) {
        $query = "SELECT user_id FROM user_id LIMIT $batch_size OFFSET $offset";
        $result = $connect->query($query);
        
        if($result->num_rows == 0) break;
        
        $user_ids = [];
        while($row = $result->fetch_assoc()) {
            $user_ids[] = $row['user_id'];
        }
        
        // Har bir batch uchun alohida yuborish
        foreach($user_ids as $user_id) {
            try {
                if($type == 'text') {
                    $response = bot('sendMessage', [
                        'chat_id' => $user_id,
                        'text' => $message,
                        'parse_mode' => 'HTML'
                    ]);
                } elseif($type == 'photo' && $file_path) {
                    $response = bot('sendPhoto', [
                        'chat_id' => $user_id,
                        'photo' => new CURLFile($file_path),
                        'caption' => $caption ?? $message,
                        'parse_mode' => 'HTML'
                    ]);
                } elseif($type == 'document' && $file_path) {
                    $response = bot('sendDocument', [
                        'chat_id' => $user_id,
                        'document' => new CURLFile($file_path),
                        'caption' => $caption ?? $message,
                        'parse_mode' => 'HTML'
                    ]);
                }
                
                if($response && $response->ok) {
                    $success_count++;
                } else {
                    $error_count++;
                    logError("Failed to send to user $user_id: " . json_encode($response));
                }
                
                // Rate limiting - har 50 xabar uchun 1 soniya kutish
                if($success_count % 50 == 0) {
                    sleep(1);
                }
                
            } catch (Exception $e) {
                $error_count++;
                logError("Exception sending to user $user_id: " . $e->getMessage());
            }
        }
        
        $offset += $batch_size;
        
        // Har batch o'tgandan keyin qisqa kutish
        usleep(100000); // 0.1 soniya
    }
    
    return [
        'success' => $success_count,
        'errors' => $error_count,
        'total' => $success_count + $error_count
    ];
}

// Avtomatik xabar yuborish funksiyasi
function autoSendMessages() {
    global $connect, $admin;
    
    // Faol xabar yuborish holatini tekshirish
    $result = $connect->query("SELECT * FROM sendusers WHERE status = 'active' ORDER BY id DESC LIMIT 1");
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Xabar yuborish holatini tekshirish
        if($row['soni'] < $row['send']) {
            // Xabar matnini olish (bu yerda siz o'z xabaringizni yozishingiz mumkin)
            $message = "🔔 Avtomatik xabar!\n\nBu xabar cron job orqali avtomatik yuborildi.\n\n⏰ Vaqt: " . date('d.m.Y H:i:s');
            
            // Xabar yuborish
            $send_result = sendToAllUsers($message);
            
            // Natijani yangilash
            $new_sent = $row['soni'] + $send_result['success'];
            $connect->query("UPDATE sendusers SET soni = '$new_sent', joriy_vaqt = '" . date('H:i:s') . "' WHERE id = '" . $row['id'] . "'");
            
            // Admin'ga hisobot yuborish
            if($send_result['success'] > 0) {
                bot('sendMessage', [
                    'chat_id' => $admin,
                    'text' => "✅ Avtomatik xabar yuborildi!\n\n📤 Yuborilgan: {$send_result['success']} ta\n❌ Xatolar: {$send_result['errors']} ta\n📊 Progress: $new_sent/{$row['send']}"
                ]);
            }
            
            // Agar barcha xabarlar yuborilgan bo'lsa, holatni o'chirish
            if($new_sent >= $row['send']) {
                $connect->query("UPDATE sendusers SET status = 'passive' WHERE id = '" . $row['id'] . "'");
                bot('sendMessage', [
                    'chat_id' => $admin,
                    'text' => "🎉 Barcha xabarlar yuborildi!\n\n📤 Jami yuborilgan: $new_sent ta"
                ]);
            }
            
            logError("Auto send completed: {$send_result['success']} sent, {$send_result['errors']} errors");
        }
    } else {
        logError("No active send jobs found");
    }
}



// Asosiy ish
try {
    logError("Cron job started");
    
    // Avtomatik xabar yuborish
    autoSendMessages();
    
    logError("Cron job completed successfully");
    
} catch (Exception $e) {
    logError("Cron job error: " . $e->getMessage());
    
    // Admin'ga xatolik haqida xabar yuborish
    bot('sendMessage', [
        'chat_id' => $admin,
        'text' => "❌ Cron job xatoligi!\n\n📝 Xatolik: " . $e->getMessage() . "\n⏰ Vaqt: " . date('d.m.Y H:i:s')
    ]);
}

?>
