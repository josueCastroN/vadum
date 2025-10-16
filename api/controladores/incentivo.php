<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../03_utilidades.php';
require_once __DIR__ . '/../seguridad.php';

/*
 Regla de negocio (montos fijos):
  - Físico: 20%  = $600  (proporcional a cal_física 0..100)
  - Cursos: 10%  = $300  (promedio cursos aprobados 0..100)
  - Satisf.:10%  = $300  (promedio mes 0..100)
  - Desemp.:10%  = $300  (cal mensual 0..100)
  - Aliment.:50% = $1600 (penaliza faltas del mes: pago = 1600 * max(0, 1 - faltas/3))
  - Acta vigente => incentivo total = $0
  - Antigüedad: mínimo 3 meses al fin de mes, salvo autorización de gerente (tabla autorizacion_incentivo)
*/

const M_FISICO = 600.00;
const M_CURSOS = 300.00;
const M_SATISF = 300.00;
const M_DESEMP = 300.00;
const M_ALIMEN = 1600.00;

$pdo    = obtener_conexion();
$no_emp = trim($_GET['no_emp'] ?? '');
$mes    = trim($_GET['mes'] ?? ''); // YYYY-MM-01


$u = usuario_actual();
if ($u && $u['rol']==='vigilante') {
  if (($u['empleado_no_emp'] ?? '') !== $no_emp) {
    enviar_json(['ok'=>false,'error'=>'Sólo puedes consultar tu propio incentivo'],403);
  }
}


if ($no_emp==='' || $mes==='') enviar_json(['ok'=>false,'error'=>'Faltan no_emp o mes (YYYY-MM-01)'],400);

// 1) Validaciones de elegibilidad
$ant_meses = antiguedad_meses_al_mes($pdo, $no_emp, $mes);
$autorizado = tiene_autorizacion($pdo, $no_emp, $mes);
if ($ant_meses < 3 && !$autorizado) {
    enviar_json(['ok'=>true,'elegible'=>false,'motivo'=>'Antigüedad menor a 3 meses y sin autorización de gerente','total'=>0,'detalles'=>[]]);
}
if (acta_vigente($pdo, $no_emp, $mes)) {
    enviar_json(['ok'=>true,'elegible'=>false,'motivo'=>'Acta administrativa vigente en el mes','total'=>0,'detalles'=>[]]);
}

// 2) Componentes (0..100 → monto proporcional)
$fis  = ultima_cal_fisica_mes($pdo, $no_emp, $mes);          // 0..100 o null
$cur  = promedio_cursos($pdo, $no_emp, $mes);                // 0..100 o null
$sat  = promedio_satisf_mes($pdo, $no_emp, $mes);            // 0..100 o null
$des  = cal_desempeno_mes($pdo, $no_emp, $mes);              // 0..100 o null
$fal  = faltas_del_mes($pdo, $no_emp, $mes);                 // entero

$pag_fis = $fis !== null ? round(M_FISICO * ($fis/100), 2) : 0.00;
$pag_cur = $cur !== null ? round(M_CURSOS * ($cur/100), 2) : 0.00;
$pag_sat = $sat !== null ? round(M_SATISF * ($sat/100), 2) : 0.00;
$pag_des = $des !== null ? round(M_DESEMP * ($des/100), 2) : 0.00;

// Alimentación: 3 faltas = 0%; 1 falta = -1/3; 2 faltas = -2/3
$factor_alim = max(0.0, 1.0 - min(3,$fal)/3.0);
$pag_ali = round(M_ALIMEN * $factor_alim, 2);

$total = $pag_fis + $pag_cur + $pag_sat + $pag_des + $pag_ali;

enviar_json([
  'ok'        => true,
  'elegible'  => true,
  'no_emp'    => $no_emp,
  'mes'       => $mes,
  'totales'   => [
    'total_pesos' => $total,
    'componentes' => [
      'fisico'       => ['cal'=>$fis, 'monto'=>$pag_fis, 'base'=>M_FISICO],
      'cursos'       => ['cal'=>$cur, 'monto'=>$pag_cur, 'base'=>M_CURSOS],
      'satisfaccion' => ['cal'=>$sat, 'monto'=>$pag_sat, 'base'=>M_SATISF],
      'desempeno'    => ['cal'=>$des, 'monto'=>$pag_des, 'base'=>M_DESEMP],
      'alimentacion' => ['faltas'=>$fal, 'factor'=>$factor_alim, 'monto'=>$pag_ali, 'base'=>M_ALIMEN],
    ],
  ],
  'notas' => [
    'antiguedad_meses' => $ant_meses,
    'autorizado_gerente' => $autorizado,
    'reglas' => 'Acta vigente anula; Antigüedad ≥3 meses o autorización; Alimentación penaliza faltas (1=-1/3,2=-2/3,3=0).'
  ]
]);
