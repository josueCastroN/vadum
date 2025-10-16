<?php
// =============================================
// Convierte passwords en claro a bcrypt (demo)
// =============================================
require_once __DIR__ . '/02_bd.php';
$pdo = obtener_conexion();

// Lista de usuarios a convertir (ajusta los que tengas)
$usuarios = ['admin','sup1','vig1','cli1'];
$nuevaclave = '123456'; // la misma que usabas, pero ahora quedará hasheada

$hash = password_hash($nuevaclave, PASSWORD_BCRYPT);
$in = str_repeat('?,', count($usuarios)-1) . '?';

$st = $pdo->prepare("UPDATE usuarios SET password_hash=? WHERE usuario IN ($in)");
$params = array_merge([$hash], $usuarios);
$st->execute($params);

echo "Hecho. Usuarios convertidos a bcrypt.";
