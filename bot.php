<?php
ob_start();

ini_set('display_errors', 1); // Set to 1 to display errors, 0 to hide
ini_set('display_startup_errors', 1); // Display errors occurring during PHP's startup sequence
error_reporting(E_ALL); // Report all types of errors



// Konfiguratsiya faylini yuklash
require_once("config.php");

$user = "iiibragimovzuxriddin";
$soat = date('H:i');
$sana = date("d.m.Y");

// Ma'lumotlar bazasi ulanishi
require_once("sql.php");

// Yordamchi funksiyalar
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, 'bot_errors.log');
}

// Kanal ma'lumotlarini tekshirish funksiyasi
function checkChannelData($channelID, $title, $link, $type) {
    $debug_info = "Channel Debug:\n";
    $debug_info .= "ID: $channelID\n";
    $debug_info .= "Title: $title\n";
    $debug_info .= "Link: $link\n";
    $debug_info .= "Type: $type\n";
    
    // Kanal ma'lumotlarini API orqali tekshirish
    $response = bot('getChat', ['chat_id' => $channelID]);
    if($response && isset($response->result)) {
        $debug_info .= "API Response: OK\n";
        $debug_info .= "API Title: " . $response->result->title . "\n";
    } else {
        $debug_info .= "API Response: FAILED\n";
        $debug_info .= "Error: " . json_encode($response) . "\n";
    }
    
    logError($debug_info);
    return $debug_info;
}

function admin($id) {
    global $admin;
    return $id == $admin;
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

// Kanal obunasi tekshirish funksiyasi
function joinchat($id) {
global $connect;
$result = $connect->query("SELECT * FROM `kanallar`");
    
    if($result && $result->num_rows > 0 && !admin($id)) {
$no_subs = 0;
$button = [];
        
        while($row = $result->fetch_assoc()) {
$type = $row['type'];
$link = $row['link'];
$channelID = $row['channelID'];
$title = $row['title'];
            
            if($type == "lock" || $type == "request") {
                if($type == "request") {
                    $check = $connect->query("SELECT * FROM `requests` WHERE user_id = '$id' AND chat_id = '$channelID'");
                    if($check && $check->num_rows > 0) {
                        $button[] = ['text' => "✅ $title", 'url' => $link];
                    } else {
                        $button[] = ['text' => "❌ $title", 'url' => $link];
$no_subs++;
}
                } elseif($type == "lock") {
                    $response = bot('getChatMember', ['chat_id' => $channelID, 'user_id' => $id]);
                    $check = ($response && isset($response->result->status)) ? $response->result->status : 'left';
                    if($check == "left") {
                        $button[] = ['text' => "❌ $title", 'url' => $link];
$no_subs++;
                    } else {
                        $button[] = ['text' => "✅ $title", 'url' => $link];
                    }
                }
            } elseif($type == "social") {
                $decoded_title = base64_decode($title);
                if($decoded_title !== false) {
                    $button[] = ['text' => $decoded_title, 'url' => $link];
                } else {
                    $button[] = ['text' => $title, 'url' => $link];
                }
            }
        }
        
        if($no_subs > 0) {
            $button[] = ['text' => "✅ Tekshirish", 'callback_data' => "checkSub"];
            $keyboard2 = array_chunk($button, 1);
            $keyboard = json_encode(['inline_keyboard' => $keyboard2]);
            
            bot('sendMessage', [
                'chat_id' => $id,
                'text' => "<b>❌ Kechirasiz botimizdan foydalanishdan oldin ushbu kanallarga a'zo bo'lishingiz kerak.</b>",
                'parse_mode' => 'html',
                'reply_markup' => $keyboard
            ]);
            return false;
        }
    }
    return true;
}

// Yaxshilangan send funksiyasi - katta foydalanuvchilar bazasi uchun
function sendToAllUsers($message, $type = 'text', $file_path = null, $caption = null) {
    global $connect;
    
    // Foydalanuvchilarni batched qilib olish
    $batch_size = 30; // Telegram API limiti
    $offset = 0;
    $success_count = 0;
    $error_count = 0;
    
    // Local file validation (skip for Telegram file_id)
    if(($type == 'photo' || $type == 'document' || $type == 'video' || $type == 'audio') && $file_path) {
        if(file_exists($file_path)) {
            // File size check (Telegram limits: ~50MB docs, ~10MB photos; videos/audios vary by bot settings)
            $file_size = filesize($file_path);
            if($type == 'document' && $file_size > 50 * 1024 * 1024) {
                logError("Document too large: " . ($file_size / 1024 / 1024) . "MB");
                return [
                    'success' => 0,
                    'errors' => 0,
                    'total' => 0,
                    'error' => 'Document too large (max 50MB)'
                ];
            }
            if($type == 'photo' && $file_size > 10 * 1024 * 1024) {
                logError("Photo too large: " . ($file_size / 1024 / 1024) . "MB");
                return [
                    'success' => 0,
                    'errors' => 0,
                    'total' => 0,
                    'error' => 'Photo too large (max 10MB)'
                ];
            }
        }
        // If file does not exist locally, we assume it's a Telegram file_id and skip size checks
    }
    
    while(true) {
        $query = "SELECT user_id FROM user_id LIMIT $batch_size OFFSET $offset";
        $result = $connect->query($query);
        
        if(!$result || $result->num_rows == 0) break;
        
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
                    // Photo: local path or Telegram file_id
                    $photo_data = file_exists($file_path) ? new CURLFile($file_path) : $file_path;
                    $response = bot('sendPhoto', [
                        'chat_id' => $user_id,
                        'photo' => $photo_data,
                        'caption' => $caption ?? $message,
                        'parse_mode' => 'HTML'
                    ]);
                } elseif($type == 'document' && $file_path) {
                    // Document: local path or Telegram file_id
                    $document_data = file_exists($file_path) ? new CURLFile($file_path) : $file_path;
                    $response = bot('sendDocument', [
                        'chat_id' => $user_id,
                        'document' => $document_data,
                        'caption' => $caption ?? $message,
                        'parse_mode' => 'HTML'
                    ]);
                } elseif($type == 'video' && $file_path) {
                    // Video: local path or Telegram file_id
                    $video_data = file_exists($file_path) ? new CURLFile($file_path) : $file_path;
                    $response = bot('sendVideo', [
                        'chat_id' => $user_id,
                        'video' => $video_data,
                        'caption' => $caption ?? $message,
                        'parse_mode' => 'HTML'
                    ]);
                } elseif($type == 'audio' && $file_path) {
                    // Audio: local path or Telegram file_id
                    $audio_data = file_exists($file_path) ? new CURLFile($file_path) : $file_path;
                    $response = bot('sendAudio', [
                        'chat_id' => $user_id,
                        'audio' => $audio_data,
                        'caption' => $caption ?? $message,
                        'parse_mode' => 'HTML'
                    ]);
                } else {
                    // Default text message
                    $response = bot('sendMessage', [
                        'chat_id' => $user_id,
                        'text' => $message,
                        'parse_mode' => 'HTML'
                    ]);
                }
                
                if($response && isset($response->ok) && $response->ok) {
                    $success_count++;
                } else {
                    $error_count++;
                    $error_msg = isset($response->description) ? $response->description : 'Unknown error';
                    logError("Failed to send to user $user_id: $error_msg");
                }
                
                // Rate limiting - har 30 xabar uchun 1 soniya kutish (Telegram API limiti)
                if(($success_count + $error_count) % 30 == 0) {
                    sleep(1);
                }
                
            } catch (Exception $e) {
                $error_count++;
                logError("Exception sending to user $user_id: " . $e->getMessage());
            }
        }
        
        $offset += $batch_size;
        
        // Har batch o'tgandan keyin qisqa kutish
        usleep(200000); // 0.2 soniya (Telegram API limitini oshirmaslik uchun)
    }
    
    return [
        'success' => $success_count,
        'errors' => $error_count,
        'total' => $success_count + $error_count
    ];
}

// Foydalanuvchi ro'yxatdan o'tkazish funksiyasi
function checkUserRegistration($chat_id, $connect) {
    $check_user = mysqli_query($connect, "SELECT * FROM users WHERE chat_id = '$chat_id'");
    return mysqli_fetch_assoc($check_user);
}

function requestPhoneNumber($chat_id) {
    $keyboard = json_encode([
        'keyboard' => [[[
            'text' => "📱 Telefon raqam yuborish",
            'request_contact' => true
        ]]],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ]);

    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "Iltimos, telefon raqamingizni yuboring 👇",
        'reply_markup' => $keyboard
    ]);
}

function checkSubscriptionAndProceed($chat_id, $connect) {
    if (joinchat($chat_id) == true) {
        $user_data = checkUserRegistration($chat_id, $connect);

        if ($user_data) {
            $ticket_number = $user_data['ticket_number'];
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "Siz konkursda ro'yxatdan o'tgansiz. Tartib raqamingiz: $ticket_number"
            ]);
        } else {
            requestPhoneNumber($chat_id);
            mysqli_query($connect, "UPDATE user_id SET step = '1' WHERE user_id = '$chat_id'");
        }
    } 
}

// Ma'lumotlarni eksport qilish funksiyasi
function exportToCSV($chat_id, $connect) {
    $query = "SELECT chat_id, first_name, last_name, username, phone_number, ticket_number, sana FROM users ORDER BY ticket_number";
    $result = mysqli_query($connect, $query);

    if (!$result) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "Ma'lumotlarni olishda xatolik: " . mysqli_error($connect)
        ]);
        return;
    }
    
    if (mysqli_num_rows($result) == 0) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Hech qanday foydalanuvchi topilmadi!"
        ]);
        return;
    }

    $filename = "users_" . date('Y-m-d_H-i-s') . ".csv";
    $filepath = __DIR__ . "/" . $filename;

    $file = fopen($filepath, 'w');
    if (!$file) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ CSV fayl yaratishda xatolik yuz berdi!"
        ]);
        return;
    }
    
    fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for UTF-8
    fputcsv($file, ['Chat ID', 'Ism', 'Familiya', 'Username', 'Telefon raqam', 'Tartib raqami', 'Ro\'yxatdan o\'tgan sana']);

    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($file, [
            $row['chat_id'],
            $row['first_name'],
            $row['last_name'],
            $row['username'],
            $row['phone_number'],
            $row['ticket_number'],
            $row['sana']
        ]);
    }

    fclose($file);

    global $token;
    $curl = curl_init();
    if (!$curl) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ CURL xatolik yuz berdi!"
        ]);
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        return;
    }
    
    curl_setopt($curl, CURLOPT_URL, "https://api.telegram.org/bot$token/sendDocument");
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, [
        'chat_id' => $chat_id,
        'document' => new CURLFile($filepath),
        'caption' => "Foydalanuvchilar ro'yxati CSV formatda - " . date('d.m.Y H:i')
    ]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($curl);
    if (curl_error($curl)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ CURL xatolik: " . curl_error($curl)
        ]);
    }
    curl_close($curl);

    if (file_exists($filepath)) {
        unlink($filepath);
    }
}

// Statistika funksiyasi
function getStatistics($connect) {
    $stats = [];
    
    // Umumiy foydalanuvchilar
    $result = $connect->query("SELECT COUNT(*) as total FROM user_id");
    $stats['total_users'] = ($result && $result->fetch_assoc()) ? $result->fetch_assoc()['total'] : 0;
    
    // Ro'yxatdan o'tgan foydalanuvchilar
    $result = $connect->query("SELECT COUNT(*) as registered FROM users");
    $stats['registered_users'] = ($result && $result->fetch_assoc()) ? $result->fetch_assoc()['registered'] : 0;
    
    // Bugungi ro'yxatdan o'tganlar
    $today = date('Y-m-d');
    $result = $connect->query("SELECT COUNT(*) as today FROM users WHERE DATE(sana) = '$today'");
    $stats['today_registered'] = ($result && $result->fetch_assoc()) ? $result->fetch_assoc()['today'] : 0;
    
    // Oxirgi 7 kundagi ro'yxatdan o'tganlar
    $week_ago = date('Y-m-d', strtotime('-7 days'));
    $result = $connect->query("SELECT COUNT(*) as week FROM users WHERE DATE(sana) >= '$week_ago'");
    $stats['week_registered'] = ($result && $result->fetch_assoc()) ? $result->fetch_assoc()['week'] : 0;
    
    return $stats;
}

// Asosiy update ma'lumotlarini olish
$upDate = json_decode(file_get_contents('php://input'));
$message = isset($upDate->message) ? $upDate->message : null;

// Message ma'lumotlarini null-safe olish
$cid = ($message && isset($message->chat->id)) ? $message->chat->id : null;
$name = ($message && isset($message->chat->first_name)) ? $message->chat->first_name : '';
$tx = ($message && isset($message->text)) ? $message->text : '';
$mid = ($message && isset($message->message_id)) ? $message->message_id : null;
$type = ($message && isset($message->chat->type)) ? $message->chat->type : '';
$text = ($message && isset($message->text)) ? $message->text : '';
$uid = ($message && isset($message->from->id)) ? $message->from->id : null;
$id = ($message && isset($message->from->id)) ? $message->from->id : null;
$name = ($message && isset($message->from->first_name)) ? $message->from->first_name : '';
$familya = ($message && isset($message->from->last_name)) ? $message->from->last_name : '';
$premium = ($message && isset($message->from->is_premium)) ? $message->from->is_premium : false;
$bio = ($message && isset($message->from->about)) ? $message->from->about : '';
$username = ($message && isset($message->from->username)) ? $message->from->username : '';
$chat_id = ($message && isset($message->chat->id)) ? $message->chat->id : null;
$message_id = ($message && isset($message->message_id)) ? $message->message_id : null;
$reply = ($message && isset($message->reply_to_message->text)) ? $message->reply_to_message->text : '';
$nameru = "<a href='tg://user?id=$uid'>$name $familya</a>";

$caption = ($message && isset($message->caption)) ? $message->caption : '';
$photo = ($message && isset($message->photo)) ? $message->photo : null;
$video = ($message && isset($message->video)) ? $message->video : null;
$file_id = ($video && isset($video->file_id)) ? $video->file_id : '';
$file_name = ($video && isset($video->file_name)) ? $video->file_name : '';
$file_size = ($video && isset($video->file_size)) ? $video->file_size : 0;
$size = $file_size/1000;
$dtype = ($video && isset($video->mime_type)) ? $video->mime_type : '';

// Inline/Callback ma'lumotlari (null-safe)
$data = isset($upDate->callback_query->data) ? $upDate->callback_query->data : '';
$qid = isset($upDate->callback_query->id) ? $upDate->callback_query->id : '';
$id = isset($upDate->inline_query->id) ? $upDate->inline_query->id : '';
$query = isset($upDate->inline_query->query) ? $upDate->inline_query->query : '';
$query_id = isset($upDate->inline_query->from->id) ? $upDate->inline_query->from->id : '';
$cid2 = isset($upDate->callback_query->message->chat->id) ? $upDate->callback_query->message->chat->id : '';
$mid2 = isset($upDate->callback_query->message->message_id) ? $upDate->callback_query->message->message_id : '';
$callfrid = isset($upDate->callback_query->from->id) ? $upDate->callback_query->from->id : '';
$callname = isset($upDate->callback_query->from->first_name) ? $upDate->callback_query->from->first_name : '';
$calluser = isset($upDate->callback_query->from->username) ? $upDate->callback_query->from->username : '';
$surname = isset($upDate->callback_query->from->last_name) ? $upDate->callback_query->from->last_name : '';
$about = isset($upDate->callback_query->from->about) ? $upDate->callback_query->from->about : '';
$nameuz = "<a href='tg://user?id=$callfrid'>$callname $surname</a>";

$chat_join_request = $upDate->chat_join_request ?? null;
$join_chat_id = $chat_join_request->chat->id ?? '';
$join_user_id = $chat_join_request->from->id ?? '';

// Chat join request qayd qilish
if(isset($chat_join_request) && !empty($join_user_id) && !empty($join_chat_id)) {
    // Avval mavjud bo'lsa o'chirib, keyin yangi qo'shish (duplicate bo'lishini oldini olish)
    $connect->query("DELETE FROM requests WHERE user_id = '$join_user_id' AND chat_id = '$join_chat_id'");
    $connect->query("INSERT INTO requests (user_id, chat_id) VALUES ('$join_user_id', '$join_chat_id')");
    logError("Chat join request recorded: User $join_user_id -> Channel $join_chat_id");
}

// Admin tekshirish
if($cid && $cid == $admin) {
    $admin = $cid;
}

// sendusers jadvali uchun avtomatik yozuv yaratish o'chirildi

// Foydalanuvchi ma'lumotlarini tekshirish va qo'shish (faqat message bo'lsa)
if($message && $cid) {
    $safeCid = intval($cid);
    if($safeCid > 0) {
        $res = mysqli_query($connect, "SELECT * FROM user_id WHERE user_id=$safeCid");
        if($res) {
            $user_id = '';
            $step = '0';
            while($a = mysqli_fetch_assoc($res)) {
                $user_id = $a['user_id'];
                $step = $a['step'];
            }
            
            $result = mysqli_query($connect, "SELECT * FROM user_id WHERE user_id = $safeCid");
            if($result) {
                $rew = mysqli_fetch_assoc($result);
                if(!$rew) {
                    mysqli_query($connect, "INSERT INTO user_id(user_id,step,sana) VALUES ('$safeCid','0','$sana | $soat')");
                }
            }
        }
    }
} else {
    $user_id = '';
    $step = '0';
}

// Callback query handler (Tekshirish)
if (($data == "checkSub" || $data == "result") && $cid2) {
    bot('deleteMessage', [
        'chat_id' => $cid2,
        'message_id' => $mid2
    ]);
    checkSubscriptionAndProceed($cid2, $connect);
}

// Start command
if ($text == "/start" && $cid) {
    // Bir martalik salomlashuv xabari (majburiy obunadan oldin)
    $welcomed = false;
    $q = mysqli_query($connect, "SELECT temp_message FROM user_id WHERE user_id = '" . intval($cid) . "'");
    if($q) {
        $r = mysqli_fetch_assoc($q);
        $welcomed = isset($r['temp_message']) && $r['temp_message'] === 'welcomed';
    }
    if(!$welcomed) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "Assalomu alaykum! Botdan foydalanishdan oldin kanallarga obuna bo'ling, so'ng davom etamiz.",
        ]);
        mysqli_query($connect, "UPDATE user_id SET temp_message='welcomed' WHERE user_id='" . intval($cid) . "'");
    }
    
    checkSubscriptionAndProceed($cid, $connect);
}

// Telefon raqam qabul qilish
if (isset($message->contact)) {
    $chat_id = $message->chat->id;
    $phone_number = $message->contact->phone_number;
    $username = $message->from->username ?? '';
    $first_name = $message->from->first_name ?? '';
    $last_name = $message->from->last_name ?? '';

    $checkStep = mysqli_query($connect, "SELECT step FROM user_id WHERE user_id = '$chat_id'");
    if($checkStep) {
    $stepRow = mysqli_fetch_assoc($checkStep);
    $step = $stepRow['step'] ?? 0;
    } else {
        $step = 0;
    }

    if ($step == 1) {
        $check = mysqli_query($connect, "SELECT * FROM users WHERE chat_id = '$chat_id'");
        if ($check && mysqli_num_rows($check) > 0) {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "Siz avval ro'yxatdan o'tgansiz."
            ]);
        } else {
            $result = mysqli_query($connect, "SELECT MAX(ticket_number) as max_ticket FROM users");
            if($result) {
            $row = mysqli_fetch_assoc($result);
            $last_ticket = $row['max_ticket'] ?? 99;
            $new_ticket = $last_ticket + 1;
            } else {
                $new_ticket = 100;
            }

            $sql = "INSERT INTO users (chat_id, phone_number, username, first_name, last_name, ticket_number, sana) 
                    VALUES ('$chat_id', '$phone_number', '$username', '$first_name', '$last_name', '$new_ticket', '$sana | $soat')";

            if (mysqli_query($connect, $sql)) {
                $removeKeyboard = json_encode(['remove_keyboard' => true]);
                $sentMessage = bot('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "Ma'lumot qabul qilindi...",
                    'reply_markup' => $removeKeyboard
                ]);

                $message_id = $sentMessage->result->message_id;
                bot('deleteMessage', [
                    'chat_id' => $chat_id,
                    'message_id' => $message_id
                ]);

                bot('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "Tabriklaymiz! Sizga $new_ticket raqami berildi."
                ]);

                 $keyboard = json_encode([
        'keyboard' => [
            [['text' => "📄 Profil"], ['text' => "ℹ️ Konkurs haqida"]]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ]);

    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "🎉",
        'reply_markup' => $keyboard
    ]);

                mysqli_query($connect, "UPDATE user_id SET step = '0' WHERE user_id = '$chat_id'");
            } else {
                bot('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "Xatolik yuz berdi: " . mysqli_error($connect)
                ]);
            }
        }
    }
}

// Profil ko'rish
if ($text == "📄 Profil") {
    $check_user = mysqli_query($connect, "SELECT * FROM users WHERE chat_id = '$chat_id'");
    if($check_user) {
    $user_data = mysqli_fetch_assoc($check_user);

    if ($user_data) {
        $ticket_number = $user_data['ticket_number'];
        $phone_number = $user_data['phone_number'];
        $registration_date = $user_data['sana'];

        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📄 Profil ma'lumotlari:\n\n🔢 Tartib raqam: $ticket_number\n📱 Telefon raqam: $phone_number\n📅 Ro'yxatdan o'tgan sana: $registration_date"
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "Siz hali ro'yxatdan o'tmagansiz!"
            ]);
        }
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Ma'lumotlarni olishda xatolik yuz berdi!"
        ]);
    }
}

// Konkurs haqida ma'lumot
if ($text == "ℹ️ Konkurs haqida") {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "ℹ️ Konkurs haqida ma'lumot:\n\n📌 Bu konkursda qatnashish uchun barcha kanallarga obuna bo'lish va telefon raqamingizni yuborishingiz lozim. Yutganlar ro'yxati konkurs yakunlangandan so'ng e'lon qilinadi."
    ]);
}

// CSV eksport
if ($text == "/fayl" && $cid && $cid == $admin) {
    exportToCSV($chat_id, $connect);
}

// Xabar yuborish holatini tekshirish (admin uchun)
if ($text == "/checksend" && $cid && $cid == $admin) {
    $result = $connect->query("SELECT * FROM sendusers ORDER BY id DESC LIMIT 5");
    if($result && $result->num_rows > 0) {
        $debug_message = "📝 So'nggi 5 ta xabar holati:\n\n";
        
        while($row = $result->fetch_assoc()) {
            $status_emoji = '';
            switch($row['status']) {
                case 'pending': $status_emoji = '⏳'; break;
                case 'active': $status_emoji = '🔄'; break;
                case 'completed': $status_emoji = '✅'; break;
                case 'passive': $status_emoji = '⏸️'; break;
                default: $status_emoji = '❓'; break;
            }
            
            $debug_message .= "📝 ID: " . $row['id'] . "\n";
            $debug_message .= "   $status_emoji Holat: " . $row['status'] . "\n";
            $debug_message .= "   📤 Yuborilgan: " . $row['soni'] . "/" . $row['send'] . "\n";
            $debug_message .= "   👤 Yaratuvchi: " . $row['creator'] . "\n";
            $debug_message .= "   ⏰ Vaqt: " . $row['boshlash_vaqt'] . "\n";
            $debug_message .= "   📝 Xabar: " . (strlen($row['mid']) > 50 ? substr($row['mid'], 0, 50) . "..." : $row['mid']) . "\n\n";
        }
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $debug_message
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Hech qanday xabar topilmadi!"
        ]);
    }
}

// Xabar yuborish tizimini qayta ishga tushirish (admin uchun)
if ($text == "/resetsend" && $cid && $cid == $admin) {
    // Barcha pending xabarlarni completed qilish
    $reset_result = mysqli_query($connect, "UPDATE sendusers SET status='completed' WHERE status='pending'");
    
    if($reset_result) {
        $affected_rows = mysqli_affected_rows($connect);
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ Xabar yuborish tizimi qayta ishga tushirildi!\n\n📝 Qayta ishga tushirilgan xabarlar: $affected_rows ta\n\n💡 Endi yangi xabar yuborishni sinab ko'ring."
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Xatolik yuz berdi: " . mysqli_error($connect)
        ]);
    }
}

// Media xabar yuborish test (admin uchun)
if ($text == "/testmedia" && $cid && $cid == $admin) {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "🧪 Media xabar yuborish testi:\n\n📤 Iltimos, rasm, hujjat yoki video yuboring. Bot uni barcha foydalanuvchilarga yuboradi.\n\n💡 Test uchun kichik fayl yuboring (< 5MB)."
    ]);
    
    mysqli_query($connect, "UPDATE user_id SET step='test_media' WHERE user_id='$cid'");
    exit();
}

// Kanallar ma'lumotlarini tekshirish (admin uchun)
if ($text == "/checkchannels" && $cid && $cid == $admin) {
    $result = $connect->query("SELECT * FROM kanallar ORDER BY id");
    if($result) {
        $debug_message = "📺 Kanallar ma'lumotlari:\n\n";
        
        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $type_emoji = '';
                switch($row['type']) {
                    case 'lock': $type_emoji = '🔒'; break;
                    case 'request': $type_emoji = '📝'; break;
                    case 'social': $type_emoji = '🔗'; break;
                }
                
                if($row['type'] == 'social') {
                    $decoded_title = base64_decode($row['title']);
                    $title = ($decoded_title !== false) ? $decoded_title : $row['title'];
                } else {
                    $title = $row['title'];
                }
                
                $debug_message .= "📝 ID: " . $row['id'] . "\n";
                $debug_message .= "   $type_emoji Nomi: $title\n";
                $debug_message .= "   🔗 Link: $row[link]\n";
                $debug_message .= "   🆔 Channel ID: $row[channelID]\n";
                $debug_message .= "   📋 Turi: $row[type]\n\n";
            }
        } else {
            $debug_message .= "❌ Hech qanday kanal topilmadi!";
        }
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $debug_message
        ]);
    }
}

// Requests jadvalini tekshirish (admin uchun)
if ($text == "/checkrequests" && $cid && $cid == $admin) {
    $result = $connect->query("SELECT * FROM requests ORDER BY id");
    if($result) {
        $debug_message = "📝 Requests jadvali:\n\n";
        
        if($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $debug_message .= "👤 User ID: " . $row['user_id'] . "\n";
                $debug_message .= "   📺 Channel ID: " . $row['chat_id'] . "\n\n";
            }
        } else {
            $debug_message .= "❌ Hech qanday request topilmadi!";
        }
        
        bot('sendMessage', [
    'chat_id' => $chat_id,
            'text' => $debug_message
        ]);
    }
}

// User'ni request jadvaliga qo'shish (admin uchun)
if (strpos($text, "/addrequest") === 0 && $cid == $admin) {
    $parts = explode(' ', $text);
    if(count($parts) == 3) {
        $user_id = trim($parts[1]);
        $channel_id = trim($parts[2]);
        
        // Avval mavjud bo'lsa o'chirib, keyin yangi qo'shish
        $connect->query("DELETE FROM requests WHERE user_id = '$user_id' AND chat_id = '$channel_id'");
        $connect->query("INSERT INTO requests (user_id, chat_id) VALUES ('$user_id', '$channel_id')");
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ User $user_id kanal $channel_id uchun request jadvaliga qo'shildi!"
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Noto'g'ri format!\n\nFoydalanish: /addrequest [user_id] [channel_id]"
        ]);
    }
}

// User'ni request jadvalidan o'chirish (admin uchun)
if (strpos($text, "/removerequest") === 0 && $cid == $admin) {
    $parts = explode(' ', $text);
    if(count($parts) == 3) {
        $user_id = trim($parts[1]);
        $channel_id = trim($parts[2]);
        
        $connect->query("DELETE FROM requests WHERE user_id = '$user_id' AND chat_id = '$channel_id'");
        
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ User $user_id kanal $channel_id uchun request jadvalidan o'chirildi!"
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Noto'g'ri format!\n\nFoydalanish: /removerequest [user_id] [channel_id]"
        ]);
    }
}

// Kanal linkini tuzatish (admin uchun)
if (strpos($text, "/fixlink") === 0 && $cid == $admin) {
    $parts = explode(' ', $text);
    if(count($parts) == 2) {
        $channel_id = trim($parts[1]);
        
        // Kanal ma'lumotlarini olish
        $result = $connect->query("SELECT * FROM kanallar WHERE id = '$channel_id'");
        if($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $channelID = $row['channelID'];
            $title = $row['title'];
            
            // Yangi invite link olish
            $invite_response = bot('exportChatInviteLink', ['chat_id' => $channelID]);
            
            if($invite_response && isset($invite_response->result)) {
                $new_link = $invite_response->result;
                
                // Database'da yangilash
                $update_sql = "UPDATE kanallar SET link = ? WHERE id = ?";
                $stmt = $connect->prepare($update_sql);
                $stmt->bind_param("si", $new_link, $channel_id);
                
                if($stmt->execute()) {
                    bot('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "✅ Kanal linki tuzatildi!\n\n📝 Kanal: $title\n🔗 Yangi link: $new_link"
                    ]);
                } else {
                    bot('sendMessage', [
                        'chat_id' => $chat_id,
                        'text' => "❌ Database xatolik: " . $connect->error
                    ]);
                }
                $stmt->close();
                
            } else {
                bot('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "❌ Yangi link olishda xatolik!\n\nXatolik: " . json_encode($invite_response)
                ]);
            }
        } else {
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ Kanal topilmadi! ID: $channel_id"
            ]);
        }
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Noto'g'ri format!\n\nFoydalanish: /fixlink [kanal_id]\n\nKanal ID'ni /checkchannels buyrug'i orqali ko'ring"
        ]);
    }
}

// Barcha kanal linklarini avtomatik tuzatish (admin uchun)
if ($text == "/fixalllinks" && $cid == $admin) {
    $result = $connect->query("SELECT * FROM kanallar WHERE type IN ('lock', 'request')");
    $fixed_count = 0;
    $error_count = 0;
    $message = "🔧 Barcha kanal linklari tekshirilmoqda...\n\n";
    
    if($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $channelID = $row['channelID'];
            $title = $row['title'];
            
            // Yangi invite link olish
            $invite_response = bot('exportChatInviteLink', ['chat_id' => $channelID]);
            
            if($invite_response && isset($invite_response->result)) {
                $new_link = $invite_response->result;
                
                // Database'da yangilash
                $update_sql = "UPDATE kanallar SET link = ? WHERE channelID = ?";
                $stmt = $connect->prepare($update_sql);
                $stmt->bind_param("ss", $new_link, $channelID);
                
                if($stmt->execute()) {
                    $fixed_count++;
                    $message .= "✅ $title - tuzatildi\n";
                } else {
                    $error_count++;
                    $message .= "❌ $title - database xatolik\n";
                }
                $stmt->close();
                
            } else {
                $error_count++;
                $message .= "❌ $title - API xatolik\n";
            }
            
            // API limitini oshirmaslik uchun kutish
            usleep(100000); // 0.1 soniya
        }
        
        $message .= "\n📊 Natija:\n✅ Tuzatilgan: $fixed_count ta\n❌ Xatolar: $error_count ta";
        
    } else {
        $message = "❌ Hech qanday kanal topilmadi!";
    }
    
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $message
    ]);
}

// Admin panel
$panel = json_encode([
    'inline_keyboard' => [
        [['text' => "📫 Userga Xabar", 'callback_data' => "send"]],
        [['text' => "📊 Statistika", 'callback_data' => "stat"]],
        [['text' => "🗒️ Xabar Xolati", 'callback_data' => "holat"]],
        [['text' => "🛑 Xabarni toʻxtatish", 'callback_data' => "off"]],
        [['text' => "Ommaviy", 'callback_data' => "request-false"]],
        [['text' => "So'rov qabul qiluvchi", 'callback_data' => "request-true"]],
        [['text' => "Ixtiyoriy havola", 'callback_data' => "socialnetwork"]],
        [['text' => "📋 CSV yuklab olish", 'callback_data' => "export_csv"]],
        [['text' => "📺 Kanallar ro'yxati", 'callback_data' => "list_channels"]],
        [['text' => "🗑️ Kanal o'chirish", 'callback_data' => "delete_channel"]]
    ]
]);

$orqaga = json_encode([
    'inline_keyboard' => [
        [['text' => "🔄 Orqaga", 'callback_data' => "back"]]
    ]
]);

// Admin panel ochish
if($text == "/panel" && $cid == $admin) {
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "❕Admin Panel ochildi!",
        'parse_mode' => 'html',
        'reply_to_message_id' => $mid,
        'reply_markup' => $panel
    ]);
    mysqli_query($connect, "UPDATE user_id SET step ='null' WHERE user_id='$cid'");
exit();
}

// Callback query handlers
if($data == "back") {
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "❗<b>Bosh menu</b>",
        'parse_mode' => 'html',
        'disable_web_page_preview' => true,
        'reply_markup' => $panel
    ]);
    mysqli_query($connect, "UPDATE user_id SET step ='null' WHERE user_id='$cid2'");
exit();
}

if($data == "stat") {
$res = mysqli_query($connect, "SELECT * FROM user_id");
$us = mysqli_num_rows($res);
    $res = mysqli_query($connect, "SELECT * FROM users");
    $registered = mysqli_num_rows($res);
    
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "<b>📊 Statistika</b>\n\n👥 Umumiy foydalanuvchilar: $us ta\n✅ Ro'yxatdan o'tganlar: $registered ta\n❌ Ro'yxatdan o'tmaganlar: " . ($us - $registered) . " ta",
        'parse_mode' => 'html',
        'disable_web_page_preview' => true,
        'reply_markup' => $orqaga
    ]);
    mysqli_query($connect, "UPDATE user_id SET step ='null' WHERE user_id='$cid2'");
exit();
}

if($data == "detailed_stat") {
    $stats = getStatistics($connect);
    
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "<b>📈 Batafsil statistika</b>\n\n👥 Umumiy foydalanuvchilar: {$stats['total_users']} ta\n✅ Ro'yxatdan o'tganlar: {$stats['registered_users']} ta\n📅 Bugungi ro'yxatdan o'tganlar: {$stats['today_registered']} ta\n📊 Oxirgi 7 kundagi: {$stats['week_registered']} ta",
        'parse_mode' => 'html',
        'disable_web_page_preview' => true,
        'reply_markup' => $orqaga
    ]);
    exit();
}

if($data == "export_csv") {
    exportToCSV($cid2, $connect);
    exit();
}

if($data == "send") {
    mysqli_query($connect, "UPDATE user_id SET step ='send' WHERE user_id='$cid2'");
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "📝 Yubormoqchi bo'lgan xabaringizni yuboring:\n\n💡 Qo'llab-quvvatlanadigan formatlar:\n• 📝 Matn xabar\n• 🖼️ Rasm (caption bilan)\n• 📄 Hujjat (caption bilan)\n• 🎥 Video (caption bilan)\n• 🎵 Audio (caption bilan)\n\n📤 Rasm yoki fayl yuborish uchun ularni yuboring, keyin caption yozing.",
        'parse_mode' => 'html',
        'disable_web_page_preview' => true,
        'reply_markup' => $orqaga
    ]);
    exit();
}

if($data == "test_media") {
    mysqli_query($connect, "UPDATE user_id SET step ='test_media' WHERE user_id='$cid2'");
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "🧪 Media xabar yuborish testi:\n\n📤 Iltimos, rasm, hujjat yoki video yuboring. Bot uni barcha foydalanuvchilarga yuboradi.\n\n💡 Test uchun kichik fayl yuboring (< 5MB).",
        'parse_mode' => 'html',
        'disable_web_page_preview' => true,
        'reply_markup' => $orqaga
    ]);
    exit();
}

if($data == "holat") {
    $result = mysqli_query($connect, "SELECT * FROM sendusers ORDER BY id DESC LIMIT 1");
    $row = mysqli_fetch_assoc($result);
    
    if($row) {
        $status = $row['status'];
        $sent = $row['soni'];
        $total = $row['send'];
        $progress = round(($sent / $total) * 100, 1);
        
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "<b>🗒️ Xabar holati</b>\n\n📤 Yuborilgan: $sent/$total\n📊 Progress: $progress%\n🔄 Holat: $status",
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
            'reply_markup' => $orqaga
        ]);
    } else {
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "❌ Xabar yuborish tarixi topilmadi",
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
            'reply_markup' => $orqaga
        ]);
    }
    exit();
}

if($data == "off") {
    mysqli_query($connect, "UPDATE sendusers SET status='passive' WHERE status='active'");
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "✅ Xabar yuborish to'xtatildi!",
        'parse_mode' => 'html',
        'disable_web_page_preview' => true,
        'reply_markup' => $orqaga
    ]);
    exit();
}

// Xabar yuborish
if($step == "send" && $cid == $admin) {
    if($text == "/cancel") {
        mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Xabar yuborish bekor qilindi!"
        ]);
        exit();
    }
    
    // Media fayllarni tekshirish
    $message_type = 'text';
    $file_id = '';
    $media_caption = ($message && isset($message->caption)) ? $message->caption : '';
    
    if(isset($message->photo) && !empty($message->photo)) {
        $message_type = 'photo';
        $file_id = end($message->photo)->file_id;
    } elseif(isset($message->document) && !empty($message->document)) {
        $message_type = 'document';
        $file_id = $message->document->file_id;
    } elseif(isset($message->video) && !empty($message->video)) {
        $message_type = 'video';
        $file_id = $message->video->file_id;
    } elseif(isset($message->audio) && !empty($message->audio)) {
        $message_type = 'audio';
        $file_id = $message->audio->file_id;
    }
    
    // Xabar ma'lumotlarini sendusers jadvaliga saqlash
    $current_time = date('H:i');
    $message_data = json_encode([
        'type' => $message_type,
        'file_id' => $file_id,
        'caption' => $media_caption,
        'text' => $text
    ]);
    
    $insert_result = mysqli_query($connect, "INSERT INTO sendusers (mid, soni, boshlash_vaqt, joriy_vaqt, status, send, holat, type, type2, creator) 
                           VALUES ('$message_data', '0', '$current_time', '$current_time', 'pending', '0', 'copyMessage', 'users', 'user_id', '$admin')");
    
    if(!$insert_result) {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Xabar saqlashda xatolik yuz berdi: " . mysqli_error($connect)
        ]);
        exit();
    }
    
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => "✅ Ha", 'callback_data' => "confirm_send"], ['text' => "❌ Yo'q", 'callback_data' => "cancel_send"]]
        ]
    ]);
    
    $preview_text = "📤 Xabarni yuborishni tasdiqlaysizmi?\n\n";
    if($message_type == 'text') {
        $preview_text .= "📝 Xabar: $text";
    } else {
        $preview_text .= "📁 Fayl turi: " . ucfirst($message_type) . "\n📝 Izoh: " . ($media_caption !== '' ? $media_caption : '(yo\'q)');
    }
    
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => $preview_text,
        'reply_markup' => $keyboard
    ]);
    
    mysqli_query($connect, "UPDATE user_id SET step='confirm_send' WHERE user_id='$cid'");
    exit();
}

if($data == "confirm_send") {
    // Xabar matnini sendusers jadvalidan olish - yaxshilangan qidiruv
    $result = mysqli_query($connect, "SELECT mid, id FROM sendusers WHERE status='pending' ORDER BY id DESC LIMIT 1");
    
    if(!$result) {
        logError("Database query failed in confirm_send: " . mysqli_error($connect));
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "❌ Database xatolik yuz berdi!\n\n🔍 Xatolik: " . mysqli_error($connect),
            'parse_mode' => 'html',
            'reply_markup' => $orqaga
        ]);
        exit();
    }
    
    $row = mysqli_fetch_assoc($result);
    
    if($row) {
        $message_data = json_decode($row['mid'], true);
        $message_id = $row['id'];
        
        if(!$message_data) {
            // Eski format uchun fallback
            $message_data = [
                'type' => 'text',
                'text' => $row['mid']
            ];
        }
        
        // Xabar yuborishni boshlash
        logError("Starting message broadcast: ID=$message_id, Type={$message_data['type']}");
        
        if($message_data['type'] == 'text') {
            $send_result = sendToAllUsers($message_data['text'], 'text');
        } else {
            // Media fayl yuborish
            $send_result = sendToAllUsers(
                $message_data['caption'] ?? $message_data['text'] ?? '',
                $message_data['type'],
                $message_data['file_id']
            );
        }
        
        // sendusers jadvalini yangilash - aniq ID bilan
        $total_users = $send_result['total'];
        $sent_users = $send_result['success'];
        $current_time = date('H:i');
        
        $update_result = mysqli_query($connect, "UPDATE sendusers SET soni='$sent_users', joriy_vaqt='$current_time', status='completed', send='$total_users' 
                               WHERE id='$message_id'");
        
        if(!$update_result) {
            logError("Failed to update sendusers table: " . mysqli_error($connect));
        }
        
        $result_text = "✅ Xabar yuborildi!\n\n";
        $result_text .= "📁 Turi: " . ucfirst($message_data['type']) . "\n";
        $result_text .= "📤 Yuborilgan: {$send_result['success']} ta\n";
        $result_text .= "❌ Xatolar: {$send_result['errors']} ta\n";
        $result_text .= "📊 Jami: {$send_result['total']} ta";
        
        if(isset($send_result['error'])) {
            $result_text .= "\n\n⚠️ Xatolik: {$send_result['error']}";
        }
        
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => $result_text,
            'parse_mode' => 'html',
            'reply_markup' => $orqaga
        ]);
        
        mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid2'");
        
        logError("Message broadcast completed: ID=$message_id, Type={$message_data['type']}, Success={$send_result['success']}, Errors={$send_result['errors']}");
    } else {
        // Debug ma'lumotlari bilan xatolik
        $debug_result = mysqli_query($connect, "SELECT COUNT(*) as total FROM sendusers WHERE status='pending'");
        $debug_row = mysqli_fetch_assoc($debug_result);
        $pending_count = $debug_row['total'];
        
        // Barcha xabarlarni ko'rish
        $all_messages = mysqli_query($connect, "SELECT id, status, creator, mid FROM sendusers ORDER BY id DESC LIMIT 3");
        $debug_info = "";
        if($all_messages) {
            while($msg_row = mysqli_fetch_assoc($all_messages)) {
                $debug_info .= "ID: {$msg_row['id']}, Status: {$msg_row['status']}, Creator: {$msg_row['creator']}\n";
            }
        }
        
        logError("Message not found in confirm_send. Pending: $pending_count, Admin: $admin, Debug: $debug_info");
        
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "❌ Xabar matni topilmadi!\n\n🔍 Debug ma'lumotlari:\n📝 Pending xabarlar: $pending_count ta\n👤 Admin ID: $admin\n\n💡 Muammo: Xabar yuborish jarayonida xatolik yuz berdi. Iltimos, qaytadan urinib ko'ring.\n\n🔧 Debug: /checksend buyrug'i bilan tekshiring.",
            'parse_mode' => 'html',
            'reply_markup' => $orqaga
        ]);
    }
    exit();
}

if($data == "cancel_send") {
    mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid2'");
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "❌ Xabar yuborish bekor qilindi!",
        'parse_mode' => 'html',
        'reply_markup' => $orqaga
    ]);
    exit();
}

// Kanal qo'shish funksiyalari
if($data == "request-false") {
    mysqli_query($connect, "UPDATE user_id SET step='add_channel_lock' WHERE user_id='$cid2'");
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "📝 Ommaviy kanal qo'shish:\n\nKanal ma'lumotlarini quyidagi formatda yuboring:\n\n<code>Kanal nomi|https://t.me/kanal|@kanal</code>\n\nMasalan:\n<code>Test Kanal|https://t.me/testkanal|@testkanal</code>",
        'parse_mode' => 'html',
        'disable_web_page_preview' => true,
        'reply_markup' => $orqaga
    ]);
    exit();
}

if($data == "request-true") {
    mysqli_query($connect, "UPDATE user_id SET step='add_channel_request' WHERE user_id='$cid2'");
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "📝 So'rov qabul qiluvchi kanal qo'shish:\n\nKanal ma'lumotlarini quyidagi formatda yuboring:\n\n<code>Kanal nomi|https://t.me/kanal|@kanal</code>\n\nMasalan:\n<code>Test Kanal|https://t.me/testkanal|@testkanal</code>",
        'parse_mode' => 'html',
        'disable_web_page_preview' => true,
        'reply_markup' => $orqaga
    ]);
    exit();
}

if($data == "socialnetwork") {
    mysqli_query($connect, "UPDATE user_id SET step='add_social' WHERE user_id='$cid2'");
    bot('editMessageText', [
        'chat_id' => $cid2,
        'message_id' => $mid2,
        'text' => "📝 Ixtiyoriy havola qo'shish:\n\nHavola ma'lumotlarini quyidagi formatda yuboring:\n\n<code>Havola nomi|https://example.com</code>\n\nMasalan:\n<code>Instagram|https://instagram.com/username</code>",
        'parse_mode' => 'html',
        'disable_web_page_preview' => true,
        'reply_markup' => $orqaga
    ]);
    exit();
}

// Kanal qo'shishni qabul qilish
if($step == "add_channel_lock" && $cid == $admin) {
    if($text == "/cancel") {
        mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Kanal qo'shish bekor qilindi!"
        ]);
        exit();
    }
    
    $parts = explode('|', $text);
    if(count($parts) == 3) {
        $title = trim($parts[0]);
        $link = trim($parts[1]);
        $channelID = trim($parts[2]);
        
        $sql = "INSERT INTO kanallar (title, link, channelID, type) VALUES (?, ?, ?, 'lock')";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("sss", $title, $link, $channelID);
        
        if($stmt->execute()) {
            bot('sendMessage', [
                'chat_id' => $cid,
                'text' => "✅ Ommaviy kanal muvaffaqiyatli qo'shildi!\n\n📝 Nomi: $title\n🔗 Havola: $link\n🆔 ID: $channelID"
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $cid,
                'text' => "❌ Xatolik yuz berdi: " . $connect->error
            ]);
        }
        $stmt->close();
    } else {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Noto'g'ri format! Iltimos, quyidagi formatda yuboring:\n\n<code>Kanal nomi|https://t.me/kanal|@kanal</code>",
            'parse_mode' => 'html'
        ]);
    }
    
    mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
    exit();
}

if($step == "add_channel_request" && $cid == $admin) {
    if($text == "/cancel") {
        mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Kanal qo'shish bekor qilindi!"
        ]);
        exit();
    }
    
    $parts = explode('|', $text);
    if(count($parts) == 3) {
        $title = trim($parts[0]);
        $link = trim($parts[1]);
        $channelID = trim($parts[2]);
        
        $sql = "INSERT INTO kanallar (title, link, channelID, type) VALUES (?, ?, ?, 'request')";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("sss", $title, $link, $channelID);
        
        if($stmt->execute()) {
            bot('sendMessage', [
                'chat_id' => $cid,
                'text' => "✅ So'rov qabul qiluvchi kanal muvaffaqiyatli qo'shildi!\n\n📝 Nomi: $title\n🔗 Havola: $link\n🆔 ID: $channelID"
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $cid,
                'text' => "❌ Xatolik yuz berdi: " . $connect->error
            ]);
        }
        $stmt->close();
    } else {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Noto'g'ri format! Iltimos, quyidagi formatda yuboring:\n\n<code>Kanal nomi|https://t.me/kanal|@kanal</code>",
            'parse_mode' => 'html'
        ]);
    }
    
    mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
    exit();
}

if($step == "add_social" && $cid == $admin) {
    if($text == "/cancel") {
        mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Havola qo'shish bekor qilindi!"
        ]);
        exit();
    }
    
    $parts = explode('|', $text);
    if(count($parts) == 2) {
        $title = base64_encode(trim($parts[0]));
        $link = trim($parts[1]);
        $channelID = 'social_' . time();
        
        $sql = "INSERT INTO kanallar (title, link, channelID, type) VALUES (?, ?, ?, 'social')";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("sss", $title, $link, $channelID);
        
        if($stmt->execute()) {
            bot('sendMessage', [
                'chat_id' => $cid,
                'text' => "✅ Ixtiyoriy havola muvaffaqiyatli qo'shildi!\n\n📝 Nomi: " . trim($parts[0]) . "\n🔗 Havola: $link"
            ]);
        } else {
            bot('sendMessage', [
                'chat_id' => $cid,
                'text' => "❌ Xatolik yuz berdi: " . $connect->error
            ]);
        }
        $stmt->close();
    } else {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Noto'g'ri format! Iltimos, quyidagi formatda yuboring:\n\n<code>Havola nomi|https://example.com</code>",
            'parse_mode' => 'html'
        ]);
    }
    
    mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
    exit();
}

// Media xabar test
if($step == "test_media" && $cid == $admin) {
    if($text == "/cancel") {
        mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Media test bekor qilindi!"
        ]);
        exit();
    }
    
    // Media fayllarni tekshirish
    $message_type = 'text';
    $file_id = '';
    $caption = $text ?: "Test xabar";
    
    if(isset($message->photo) && !empty($message->photo)) {
        $message_type = 'photo';
        $file_id = end($message->photo)->file_id;
        $caption = $text ?: "Test rasm";
    } elseif(isset($message->document) && !empty($message->document)) {
        $message_type = 'document';
        $file_id = $message->document->file_id;
        $caption = $text ?: "Test hujjat";
    } elseif(isset($message->video) && !empty($message->video)) {
        $message_type = 'video';
        $file_id = $message->video->file_id;
        $caption = $text ?: "Test video";
    } elseif(isset($message->audio) && !empty($message->audio)) {
        $message_type = 'audio';
        $file_id = $message->audio->file_id;
        $caption = $text ?: "Test audio";
    }
    
    if($message_type != 'text') {
        // Test yuborish - faqat 5 ta foydalanuvchiga
        $test_result = sendToAllUsers($caption, $message_type, $file_id);
        
        $result_text = "🧪 Media test natijasi:\n\n";
        $result_text .= "📁 Turi: " . ucfirst($message_type) . "\n";
        $result_text .= "📤 Yuborilgan: {$test_result['success']} ta\n";
        $result_text .= "❌ Xatolar: {$test_result['errors']} ta\n";
        $result_text .= "📊 Jami: {$test_result['total']} ta";
        
        if(isset($test_result['error'])) {
            $result_text .= "\n\n⚠️ Xatolik: {$test_result['error']}";
        }
        
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => $result_text
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $cid,
            'text' => "❌ Iltimos, rasm, hujjat yoki video yuboring!"
        ]);
    }
    
    mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
    exit();
}

// Xabar yuborish tasdiqlash
if($step == "confirm_send" && $cid == $admin) {
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => "✅ Ha", 'callback_data' => "confirm_send"], ['text' => "❌ Yo'q", 'callback_data' => "cancel_send"]]
        ]
    ]);
    
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => "📤 Xabarni yuborishni tasdiqlaysizmi?\n\n📝 Xabar: $text",
        'reply_markup' => $keyboard
    ]);
    
    mysqli_query($connect, "UPDATE user_id SET step='null' WHERE user_id='$cid'");
    exit();
}

// Kanallar ro'yxati
if($data == "list_channels") {
    $result = $connect->query("SELECT * FROM kanallar ORDER BY type, id");
    
    if($result && $result->num_rows > 0) {
        $message = "<b>📺 Kanallar ro'yxati:</b>\n\n";
        $counter = 1;
        
        while($row = $result->fetch_assoc()) {
            $type_emoji = '';
            switch($row['type']) {
                case 'lock': $type_emoji = '🔒'; break;
                case 'request': $type_emoji = '📝'; break;
                case 'social': $type_emoji = '🔗'; break;
            }
            
            if($row['type'] == 'social') {
                $decoded_title = base64_decode($row['title']);
                $title = ($decoded_title !== false) ? $decoded_title : $row['title'];
            } else {
                $title = $row['title'];
            }
            
            $message .= "$counter. $type_emoji <b>$title</b>\n";
            $message .= "   🔗 $row[link]\n";
            $message .= "   🆔 $row[channelID]\n\n";
            $counter++;
        }
        
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => $message,
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
            'reply_markup' => $orqaga
        ]);
    } else {
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "❌ Hech qanday kanal topilmadi!",
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
            'reply_markup' => $orqaga
        ]);
    }
    exit();
}

// Kanal o'chirish
if($data == "delete_channel") {
    $result = $connect->query("SELECT id, title, type FROM kanallar ORDER BY id");
    
    if($result && $result->num_rows > 0) {
        $keyboard = [];
        while($row = $result->fetch_assoc()) {
            if($row['type'] == 'social') {
                $decoded_title = base64_decode($row['title']);
                $title = ($decoded_title !== false) ? $decoded_title : $row['title'];
            } else {
                $title = $row['title'];
            }
            $keyboard[] = [['text' => "🗑️ $title", 'callback_data' => "del_channel_" . $row['id']]];
        }
        $keyboard[] = [['text' => "🔄 Orqaga", 'callback_data' => "back"]];
        
        $delete_keyboard = json_encode(['inline_keyboard' => $keyboard]);
        
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "🗑️ O'chirmoqchi bo'lgan kanalni tanlang:",
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
            'reply_markup' => $delete_keyboard
        ]);
    } else {
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "❌ Hech qanday kanal topilmadi!",
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
            'reply_markup' => $orqaga
        ]);
    }
    exit();
}

// Kanal o'chirishni tasdiqlash
if(strpos($data, "del_channel_") === 0) {
    $channel_id = str_replace("del_channel_", "", $data);
    
    $result = $connect->query("SELECT title, type FROM kanallar WHERE id = '$channel_id'");
    if($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if($row['type'] == 'social') {
            $decoded_title = base64_decode($row['title']);
            $title = ($decoded_title !== false) ? $decoded_title : $row['title'];
        } else {
            $title = $row['title'];
        }
        
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => "✅ Ha, o'chir", 'callback_data' => "confirm_del_" . $channel_id], 
                 ['text' => "❌ Yo'q", 'callback_data' => "back"]]
            ]
        ]);
        
        bot('editMessageText', [
            'chat_id' => $cid2,
            'message_id' => $mid2,
            'text' => "🗑️ <b>$title</b> kanalini o'chirishni tasdiqlaysizmi?",
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
            'reply_markup' => $keyboard
        ]);
    }
    exit();
}

// Kanal o'chirishni amalga oshirish
if(strpos($data, "confirm_del_") === 0) {
    $channel_id = str_replace("confirm_del_", "", $data);
    
    $result = $connect->query("SELECT title, type FROM kanallar WHERE id = '$channel_id'");
    if($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if($row['type'] == 'social') {
            $decoded_title = base64_decode($row['title']);
            $title = ($decoded_title !== false) ? $decoded_title : $row['title'];
        } else {
            $title = $row['title'];
        }
        
        if($connect->query("DELETE FROM kanallar WHERE id = '$channel_id'")) {
            bot('editMessageText', [
                'chat_id' => $cid2,
                'message_id' => $mid2,
                'text' => "✅ <b>$title</b> kanali muvaffaqiyatli o'chirildi!",
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
                'reply_markup' => $orqaga
            ]);
        } else {
            bot('editMessageText', [
                'chat_id' => $cid2,
                'message_id' => $mid2,
                'text' => "❌ Xatolik yuz berdi: " . $connect->error,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
                'reply_markup' => $orqaga
            ]);
        }
    }
    exit();
}

?>
