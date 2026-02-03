<?php
// Docker muhitida environment o'zgaruvchilardan o'qiydi
// Agar Docker ishlatilmasa, 'localhost' va boshqa default qiymatlar ishlatiladi

$host = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$database = getenv('DB_NAME') ?: 'konkurs_bot';

$connect = mysqli_connect($host, $username, $password, $database);

if (!$connect) {
    die("Bazaga ulanishda xatolik: " . mysqli_connect_error());
}

$connect->set_charset("utf8mb4");
?>