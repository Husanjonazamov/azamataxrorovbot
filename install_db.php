<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Baza jadvallarini yaratish uchun script
require_once 'config.php';
require_once 'sql.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id VARCHAR(50) NOT NULL,
        phone_number VARCHAR(20),
        username VARCHAR(100),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        ticket_number INT,
        sana VARCHAR(50),
        UNIQUE(chat_id)
    )",
    "CREATE TABLE IF NOT EXISTS user_id (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        step VARCHAR(50) DEFAULT '0',
        sana VARCHAR(50),
        temp_message VARCHAR(50) DEFAULT NULL,
        UNIQUE(user_id)
    )",
    "CREATE TABLE IF NOT EXISTS kanallar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255),
        link VARCHAR(255),
        channelID VARCHAR(100),
        type VARCHAR(50)
    )",
    "CREATE TABLE IF NOT EXISTS requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50),
        chat_id VARCHAR(100),
        sana TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS sendusers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status VARCHAR(20),
        soni INT DEFAULT 0,
        send INT DEFAULT 0,
        mid TEXT,
        creator VARCHAR(100),
        boshlash_vaqt DATETIME
    )"
];

echo "Jadvallarni tekshirish va yaratish boshlandi...<br>";

foreach ($queries as $sql) {
    if (mysqli_query($connect, $sql)) {
        echo "Jadval tekshirildi/yaratildi: " . substr($sql, 27, strpos($sql, '(', 27) - 27) . "<br>";
    } else {
        echo "Xatolik: " . mysqli_error($connect) . "<br>";
    }
}

echo "Tayyor! Endi botni ishlatib ko'ring.";
?>