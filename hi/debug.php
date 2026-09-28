// ✅ Cara log error yang bekerja di shared hosting:
error_log("DEBUG: Variabel X = " . print_r($x, true));

// ✅ Buat file log manual di folder yang diizinkan (dalam open_basedir):
$logFile = __DIR__ . '/../logs/debug.log';
if (is_writable(dirname($logFile))) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Pesan debug\n", FILE_APPEND);
}

// ✅ Tampilkan info versi PHP & path (untuk diagnosa):
<?php
echo "PHP: " . phpversion() . "<br>";
echo "Doc Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "<br>";
echo "Script: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "<br>";
echo "Open Basedir: " . ini_get('open_basedir') . "<br>";
echo "Disabled: " . ini_get('disable_functions') . "<br>";
?>
