<?php
require_once __DIR__ . '/../02_bd.php';
$pdo = obtener_conexion();
$total = $pdo->query("SELECT COUNT(*) AS t FROM puntos")->fetch()['t'] ?? 0;
enviar_json(['ok'=>true,'mensaje'=>'API VADUM OK (MariaDB)','puntos_registrados'=>(int)$total]);
