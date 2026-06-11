<?php

$dsn = 'mysql:host=127.0.0.1;dbname=kopisop;charset=utf8mb4';
$user = 'root';
$pass = '';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $pdo->query("select id_karyawan, nama_karyawan, foto_profil_path from karyawan where nama_karyawan like '%jakka%' order by id_karyawan desc limit 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $path = (string) ($row['foto_profil_path'] ?? '');
    $fullPath = $path !== '' ? (__DIR__ . '/../storage/app/public/' . $path) : '';
    $row['file_exists'] = $fullPath !== '' ? (file_exists($fullPath) ? 'yes' : 'no') : 'no';
    $row['full_path'] = $fullPath;
    $result[] = $row;
}

var_export($result ?? $rows);
