<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../03_utilidades.php';

$pdo = obtener_conexion();
$e = json_decode(file_get_contents('php://input'), true);
if (!is_array($e)) $e = $_POST;

$no_emp = trim($e['no_emp'] ?? '');
$mes    = trim($e['mes'] ?? ''); // 2025-09-01 (día 1)
$punt   = (int)($e['puntualidad'] ?? 0);
$disc   = (int)($e['disciplina'] ?? 0);
$imag   = (int)($e['imagen'] ?? 0);
$desem  = (int)($e['desempeno'] ?? 0);
$super  = trim($e['supervisor_id'] ?? 'sup');

if ($no_emp==='' || $mes==='') enviar_json(['ok'=>false,'error'=>'Faltan datos'],400);

$prom = round(($punt+$disc+$imag+$desem)/4, 3);
$cal0 = convertir_1a5_a_0a100($prom);

$sql = "INSERT INTO evaluacion_mensual
(no_emp,mes,puntualidad,disciplina,imagen,desempeno,prom_1_5,cal_desemp_0_100,supervisor_id)
VALUES (?,?,?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
puntualidad=VALUES(puntualidad),disciplina=VALUES(disciplina),imagen=VALUES(imagen),
desempeno=VALUES(desempeno),prom_1_5=VALUES(prom_1_5),cal_desemp_0_100=VALUES(cal_desemp_0_100),
supervisor_id=VALUES(supervisor_id)";
$st = $pdo->prepare($sql);
$st->execute([$no_emp,$mes,$punt,$disc,$imag,$desem,$prom,$cal0,$super]);

enviar_json(['ok'=>true,'prom_1_5'=>$prom,'cal_0_100'=>$cal0]);
