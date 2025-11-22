<?php
require_once __DIR__ . '/../01_config.php';
require_once __DIR__ . '/../02_bd.php';

if (!is_dir(RUTA_FIRMAS)) @mkdir(RUTA_FIRMAS, 0777, true);

$pdo = obtener_conexion();
$e = json_decode(file_get_contents('php://input'), true);
if (!is_array($e)) $e = $_POST;

$tipo = $e['tipo'] ?? ''; // 'mensual_empleado' | 'mensual_evaluador' | 'fisica_empleado' | 'fisica_evaluador'
$b64  = $e['firma_base64'] ?? '';

if ($tipo==='' || $b64==='') enviar_json(['ok'=>false,'error'=>'Faltan datos'],400);

$raw = preg_replace('#^data:image/\w+;base64,#i', '', $b64);
$bin = base64_decode($raw);
$nombre = $tipo . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.png';
$ruta   = RUTA_FIRMAS . '/' . $nombre;
file_put_contents($ruta, $bin);
$url = URL_BASE . '/almacenamiento/firmas/' . $nombre;

$ahora = date('Y-m-d H:i:s');

// Asegurar columnas de firmas en evaluacion_mensual si no existen
function asegurar_columnas_firmas(PDO $pdo): void {
  try {
    $st = $pdo->query("SHOW COLUMNS FROM evaluacion_mensual LIKE 'firma_empleado_url'");
    if (!$st->fetch()) {
      $pdo->exec("ALTER TABLE evaluacion_mensual
        ADD COLUMN firma_empleado_url VARCHAR(255) NULL AFTER supervisor_id,
        ADD COLUMN firma_empleado_fecha DATETIME NULL AFTER firma_empleado_url,
        ADD COLUMN firma_evaluador_url VARCHAR(255) NULL AFTER firma_empleado_fecha,
        ADD COLUMN firma_evaluador_fecha DATETIME NULL AFTER firma_evaluador_url");
    }
  } catch (Throwable $e) {
    // Si la tabla no existe aún, ignoramos; será creada por el módulo mensual cuando guarden por primera vez
  }
}

switch ($tipo) {
  case 'mensual_empleado':
    asegurar_columnas_firmas($pdo);
    $pdo->prepare("UPDATE evaluacion_mensual SET firma_empleado_url=?, firma_empleado_fecha=? WHERE no_emp=? AND mes=?")
        ->execute([$url, $ahora, $e['no_emp'] ?? '', $e['mes'] ?? '']);
    break;

  case 'mensual_evaluador':
    asegurar_columnas_firmas($pdo);
    $pdo->prepare("UPDATE evaluacion_mensual SET firma_evaluador_url=?, firma_evaluador_fecha=? WHERE no_emp=? AND mes=?")
        ->execute([$url, $ahora, $e['no_emp'] ?? '', $e['mes'] ?? '']);
    break;

  case 'fisica_evaluador':
    $pdo->prepare("UPDATE fisicas_sesion SET firma_evaluador_url=?, firma_evaluador_fecha=? WHERE id=?")
        ->execute([$url, $ahora, (int)($e['sesion_id'] ?? 0)]);
    break;

  case 'fisica_empleado':
    if (!empty($e['registro_id'])) {
      $pdo->prepare("UPDATE fisicas_registro SET firma_empleado_url=?, firma_empleado_fecha=? WHERE id=?")
          ->execute([$url, $ahora, (int)$e['registro_id']]);
    } else {
      $pdo->prepare("UPDATE fisicas_registro SET firma_empleado_url=?, firma_empleado_fecha=? WHERE sesion_id=? AND no_emp=?")
          ->execute([$url, $ahora, (int)($e['sesion_id'] ?? 0), $e['no_emp'] ?? '']);
    }
    break;

  default:
    enviar_json(['ok'=>false,'error'=>'Tipo de firma no válido'],400);
}

enviar_json(['ok'=>true,'url'=>$url]);
