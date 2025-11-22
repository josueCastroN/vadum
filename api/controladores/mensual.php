<?php
// =============================================
// Controlador de Evaluación Mensual (Guardar/Leer)
// =============================================
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../03_utilidades.php';
require_once __DIR__ . '/../seguridad.php';

$pdo = obtener_conexion();

function asegurar_tabla_mensual(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS evaluacion_mensual (
    no_emp VARCHAR(30) NOT NULL,
    mes DATE NOT NULL,
    puntualidad TINYINT NOT NULL,
    disciplina TINYINT NOT NULL,
    imagen TINYINT NOT NULL,
    desempeno TINYINT NOT NULL,
    prom_1_5 DECIMAL(5,3) NOT NULL,
    cal_desemp_0_100 DECIMAL(6,2) NOT NULL,
    supervisor_id VARCHAR(80) NULL,
    firma_empleado_url VARCHAR(255) NULL,
    firma_empleado_fecha DATETIME NULL,
    firma_evaluador_url VARCHAR(255) NULL,
    firma_evaluador_fecha DATETIME NULL,
    pdf_url VARCHAR(255) NULL,
    fecha_modificacion TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (no_emp, mes)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Normaliza columna pdf_url si no existía
  try {
    $existe = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='evaluacion_mensual' AND COLUMN_NAME='pdf_url'")->fetchColumn();
    if (!$existe) { $pdo->exec("ALTER TABLE evaluacion_mensual ADD COLUMN pdf_url VARCHAR(255) NULL AFTER firma_evaluador_fecha"); }
  } catch (Throwable $e) { /* ignore */ }
}

/* ========== POST /mensual/guardar: ALTA/ACTUALIZA ========== */
if (($_GET['ruta'] ?? '') === 'mensual/guardar') {
  requerir_roles(['admin', 'supervisor']);
  asegurar_tabla_mensual($pdo);

  $u = usuario_actual();
  $e = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];

  $no_emp = trim($e['no_emp'] ?? '');
  $mes = trim($e['mes'] ?? ''); // Espera YYYY-MM-01
  $punt = (int)($e['puntualidad'] ?? 0);
  $disc = (int)($e['disciplina'] ?? 0);
  $imag = (int)($e['imagen'] ?? 0);
  $desem = (int)($e['desempeno'] ?? 0);
  $supervisor = trim($e['supervisor_id'] ?? ($u['usuario'] ?? 'sistema'));

  if ($no_emp === '' || $mes === '') {
    enviar_json(['ok'=>false,'error'=>'Faltan datos obligatorios (No. Empleado o Mes)'],400);
  }

  // Cálculo: escala 1 o 3 -> 0..100
  $prom = round(($punt + $disc + $imag + $desem) / 4, 3);
  if (function_exists('convertir_1o3_a_0a100')) {
    $cal0 = convertir_1o3_a_0a100($prom);
  } else {
    $cal0 = round((($prom - 1.0) / 2.0) * 100.0, 2);
  }

  $sql = "INSERT INTO evaluacion_mensual
          (no_emp, mes, puntualidad, disciplina, imagen, desempeno, prom_1_5, cal_desemp_0_100, supervisor_id)
          VALUES (?,?,?,?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE
            puntualidad=VALUES(puntualidad),
            disciplina=VALUES(disciplina),
            imagen=VALUES(imagen),
            desempeno=VALUES(desempeno),
            prom_1_5=VALUES(prom_1_5),
            cal_desemp_0_100=VALUES(cal_desemp_0_100),
            supervisor_id=VALUES(supervisor_id)";
  try {
    $st = $pdo->prepare($sql);
    $st->execute([$no_emp, $mes, $punt, $disc, $imag, $desem, $prom, $cal0, $supervisor]);
    enviar_json(['ok'=>true,
                 'mensaje'=>'Evaluación guardada/actualizada',
                 'no_emp'=>$no_emp,
                 'mes'=>$mes,
                 'prom_1_5'=>$prom,
                 'cal_0_100'=>$cal0]);
  } catch (Throwable $e) {
    enviar_json(['ok'=>false,'error'=>'DB: '.$e->getMessage()],500);
  }
}

/* ========== GET /mensual/leer ========== */
if (($_GET['ruta'] ?? '') === 'mensual/leer') {
  requerir_roles(['admin','supervisor']);
  asegurar_tabla_mensual($pdo);

  $no_emp = trim($_GET['no_emp'] ?? '');
  $mes = trim($_GET['mes'] ?? '');
  if ($no_emp === '' || $mes === '') {
    enviar_json(['ok'=>false,'error'=>'Faltan No. Empleado o Mes para consultar'],400);
  }
  try {
    $st = $pdo->prepare("SELECT * FROM evaluacion_mensual WHERE no_emp=? AND mes=? LIMIT 1");
    $st->execute([$no_emp,$mes]);
    $eva = $st->fetch(PDO::FETCH_ASSOC);
    if (!$eva) enviar_json(['ok'=>false,'error'=>'Evaluación no encontrada'],404);
    enviar_json(['ok'=>true,'evaluacion'=>$eva]);
  } catch (Throwable $e) {
    enviar_json(['ok'=>false,'error'=>'DB: '.$e->getMessage()],500);
  }
}

/* ========== GET /mensual/historial ========== */
if (($_GET['ruta'] ?? '') === 'mensual/historial') {
  requerir_roles(['admin','supervisor']);
  asegurar_tabla_mensual($pdo);

  $no_emp = trim($_GET['no_emp'] ?? '');
  $sup    = trim($_GET['supervisor_id'] ?? '');
  $desdeM = trim($_GET['desde'] ?? ''); // YYYY-MM
  $hastaM = trim($_GET['hasta'] ?? ''); // YYYY-MM

  $sql = "SELECT em.mes, em.no_emp, em.puntualidad, em.disciplina, em.imagen, em.desempeno,
                 em.prom_1_5, em.cal_desemp_0_100, em.supervisor_id,
                 CONCAT(e.nombres,' ',e.apellidos) AS nombre, e.puesto
          FROM evaluacion_mensual em
          LEFT JOIN empleados e ON e.no_emp = em.no_emp
          WHERE 1=1";
  $p = [];
  if ($no_emp !== '') { $sql .= " AND em.no_emp = :ne"; $p[':ne'] = $no_emp; }
  if ($sup !== '')    { $sql .= " AND em.supervisor_id = :su"; $p[':su'] = $sup; }
  if ($desdeM !== '') { $sql .= " AND em.mes >= DATE_FORMAT(:d, '%Y-%m-01')"; $p[':d'] = $desdeM.'-01'; }
  if ($hastaM !== '') { $sql .= " AND em.mes <= LAST_DAY(DATE_FORMAT(:h, '%Y-%m-01'))"; $p[':h'] = $hastaM.'-01'; }
  $sql .= " ORDER BY em.mes DESC, em.no_emp ASC LIMIT 500";
  try {
    $st = $pdo->prepare($sql); $st->execute($p);
    enviar_json(['ok'=>true,'historial'=>$st->fetchAll()]);
  } catch (Throwable $e) {
    enviar_json(['ok'=>false,'error'=>'DB: '.$e->getMessage()],500);
  }
}

/* ========== POST /mensual/pdf_generar ========== */
if (($_GET['ruta'] ?? '') === 'mensual/pdf_generar') {
  requerir_roles(['admin','supervisor']);
  asegurar_tabla_mensual($pdo);
  $in = json_decode(file_get_contents('php://input'), true) ?? [];
  $no_emp = trim($in['no_emp'] ?? '');
  $mes    = trim($in['mes'] ?? '');
  if ($no_emp==='' || $mes==='') enviar_json(['ok'=>false,'error'=>'Faltan no_emp o mes'],400);
  $firma_emp_b64 = trim($in['firma_empleado_base64'] ?? '');
  $firma_eva_b64 = trim($in['firma_evaluador_base64'] ?? '');
  // Cargar evaluación para llenar el PDF
  $st = $pdo->prepare("SELECT em.*, CONCAT(e.nombres,' ',e.apellidos) AS nombre, e.puesto, p.nombre AS punto
                       FROM evaluacion_mensual em
                       LEFT JOIN empleados e ON e.no_emp = em.no_emp
                       LEFT JOIN puntos p ON p.id = e.punto_id
                       WHERE em.no_emp=? AND em.mes=? LIMIT 1");
  $st->execute([$no_emp,$mes]);
  $eva = $st->fetch(PDO::FETCH_ASSOC);
  if (!$eva) enviar_json(['ok'=>false,'error'=>'Evaluación no encontrada para generar PDF'],404);

  // Ruta de salida
  $ym = date('Y-m', strtotime($mes));
  $baseDir = dirname(__DIR__) . '/storage/mensual/' . $ym;
  if (!is_dir($baseDir)) @mkdir($baseDir, 0777, true);
  $fname = preg_replace('/[^A-Za-z0-9_\-]/','_', $no_emp.'_'.$mes) . '.pdf';
  $rutaPdf = $baseDir . '/' . $fname;

  // HTML base del PDF (A4)
  $sigEmp = $firma_emp_b64 ? 'data:image/png;base64,'.$firma_emp_b64 : '';
  $sigEva = $firma_eva_b64 ? 'data:image/png;base64,'.$firma_eva_b64 : '';
  $html = '<!doctype html><html><head><meta charset="utf-8"><style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
    .hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #ddd;padding-bottom:8px;margin-bottom:8px}
    .ttl{font-size:18px;font-weight:bold}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{border:1px solid #ccc;padding:6px}
    .sig-row{display:flex;justify-content:space-between;margin-top:24px}
    .sig{width:45%;text-align:center}
    .sig img{max-width:100%;max-height:120px;border:1px solid #eee}
    .muted{color:#666;font-size:11px}
  </style></head><body>';
  $html .= '<div class="hdr"><div class="ttl">Evaluación Mensual</div><div class="muted">Mes: '.htmlspecialchars($ym).'</div></div>';
  $html .= '<div><strong>No.Emp:</strong> '.htmlspecialchars($eva['no_emp']).' &nbsp; <strong>Nombre:</strong> '.htmlspecialchars($eva['nombre']??'').'
            &nbsp; <strong>Puesto:</strong> '.htmlspecialchars($eva['puesto']??'').' &nbsp; <strong>Punto:</strong> '.htmlspecialchars($eva['punto']??'').'</div>';
  $html .= '<table><thead><tr><th>Puntualidad</th><th>Disciplina</th><th>Imagen</th><th>Desempeño</th><th>Prom (1–5)</th><th>Calif (0–100)</th></tr></thead><tbody>';
  $html .= '<tr><td>'.(int)$eva['puntualidad'].'</td><td>'.(int)$eva['disciplina'].'</td><td>'.(int)$eva['imagen'].'</td><td>'.(int)$eva['desempeno'].'</td><td>'.htmlspecialchars($eva['prom_1_5']).'</td><td>'.htmlspecialchars($eva['cal_desemp_0_100']).'</td></tr>';
  $html .= '</tbody></table>';
  $html .= '<div class="sig-row">'
        . '<div class="sig">Firma del Empleado<br>'.($sigEmp? '<img src="'.$sigEmp.'">':'(sin firma)').'</div>'
        . '<div class="sig">Firma del Evaluador<br>'.($sigEva? '<img src="'.$sigEva.'">':'(sin firma)').'</div>'
        . '</div>';
  $html .= '<div class="muted" style="margin-top:12px;">Generado '.date('Y-m-d H:i').'</div>';
  $html .= '</body></html>';

  // Intentar Dompdf si está disponible
  $usadoPdf = false;
  try {
    $autoloads = [
      dirname(__DIR__,2).'/vendor/autoload.php',
      dirname(__DIR__).'/vendor/autoload.php',
    ];
    foreach ($autoloads as $a) { if (is_file($a)) { require_once $a; break; } }
    if (class_exists('Dompdf\\Dompdf')) {
      $dompdf = new Dompdf\Dompdf([ 'isRemoteEnabled' => true ]);
      $dompdf->loadHtml($html, 'UTF-8');
      $dompdf->setPaper('A4', 'portrait');
      $dompdf->render();
      file_put_contents($rutaPdf, $dompdf->output());
      $usadoPdf = true;
    }
  } catch (Throwable $e) {
    // Seguir a fallback
  }

  if (!$usadoPdf) {
    // Fallback: guardar HTML si no hay generador PDF. Cambia extensión.
    $rutaPdf = preg_replace('/\.pdf$/','.html',$rutaPdf);
    file_put_contents($rutaPdf, $html);
  }

  // Guardar URL relativa y timestamps de firma
  $rel = str_replace(dirname(__DIR__).'/','../api/',$rutaPdf); // serviremos por endpoint; guardamos ruta absoluta
  $pdf_url = $rutaPdf; // almacenamos ruta absoluta interna
  $firma_emp_fecha = $firma_emp_b64 ? date('Y-m-d H:i:s') : null;
  $firma_eva_fecha = $firma_eva_b64 ? date('Y-m-d H:i:s') : null;
  $st2 = $pdo->prepare("UPDATE evaluacion_mensual SET pdf_url=:u, firma_empleado_fecha=:fe, firma_evaluador_fecha=:fv WHERE no_emp=:ne AND mes=:m");
  $st2->execute([':u'=>$pdf_url, ':fe'=>$firma_emp_fecha, ':fv'=>$firma_eva_fecha, ':ne'=>$no_emp, ':m'=>$mes]);

  enviar_json(['ok'=>true,'pdf_path'=>$pdf_url,'pdf_hint'=>($usadoPdf?'pdf':'html_fallback')]);
}

/* ========== GET /mensual/pdf ========== */
if (($_GET['ruta'] ?? '') === 'mensual/pdf') {
  requerir_roles(['admin','supervisor','vigilante']);
  $no_emp = trim($_GET['no_emp'] ?? '');
  $mes    = trim($_GET['mes'] ?? '');
  if ($no_emp==='' || $mes==='') enviar_json(['ok'=>false,'error'=>'Faltan no_emp o mes'],400);
  $st = $pdo->prepare("SELECT pdf_url FROM evaluacion_mensual WHERE no_emp=? AND mes=? LIMIT 1");
  $st->execute([$no_emp,$mes]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row || !$row['pdf_url']) enviar_json(['ok'=>false,'error'=>'Sin documento'],404);
  $path = $row['pdf_url'];
  if (!is_file($path)) enviar_json(['ok'=>false,'error'=>'Archivo no encontrado'],404);
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if ($ext==='pdf') { header('Content-Type: application/pdf'); }
  else { header('Content-Type: text/html; charset=utf-8'); }
  header('Content-Disposition: inline; filename="evaluacion_mensual_'.$no_emp.'_'.$mes.'.'.$ext.'"');
  readfile($path);
  exit;
}
