<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Test boshlandi...<br>";

// Config yuklash
echo "1. Config yuklanyapti...<br>";
require_once("config.php");
echo "2. Config yuklandi. Token: " . substr($token, 0, 10) . "...<br>";

// SQL yuklash
echo "3. SQL yuklanyapti...<br>";
require_once("sql.php");
echo "4. SQL yuklandi. Connect: " . ($connect ? "OK" : "FAIL") . "<br>";

// Bazadagi jadvallarni tekshirish
echo "5. Jadvallar tekshirilmoqda...<br>";
$result = $connect->query("SHOW TABLES");
if ($result) {
    echo "6. Jadvallar: ";
    while ($row = $result->fetch_array()) {
        echo $row[0] . ", ";
    }
    echo "<br>";
} else {
    echo "6. Xato: " . $connect->error . "<br>";
}

echo "7. Bot.php test muvaffaqiyatli!";
?>