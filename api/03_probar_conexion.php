<?php
// =============================================
// Funciones de negocio (cálculos y helpers)
// =============================================
require_once __DIR__ . '/02_bd.php';

/** Convierte promedio 1–5 a 0–100 (2 decimales) */
function convertir_1a5_a_0a100(float $prom): float {
    return round((($prom - 1) / 4) * 100, 2);
}

/** Diferencia en meses completos entre dos fechas Y-m-d */
function meses_entre(string $desde, string $hasta): int {
    $d1 = new DateTime($desde);
    $d2 = new DateTime($hasta);
    $diff = $d1->diff($d2);
    return ($diff->y * 12) + $diff->m - ( (int)($diff->d < 0) );
}

/** Trae metas por edad desde parametros_fisicos_por_edad */
function obtener_metas_por_edad(PDO $pdo, int $edad): ?array {
    $sql = "SELECT * FROM parametros_fisicos_por_edad WHERE :edad BETWEEN edad_min AND edad_max LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':edad' => $edad]);
    return $st->fetch() ?: null;
}

/** Calcula 0–100 de la evaluación física a partir de metas y valores */
function calcular_componentes_fisica(PDO $pdo, int $edad, array $v): array {
    // $v = ['m'=>metros12, 's'=>seg100, 'ab'=>abd, 'lg'=>lagart, 'br'=>barras, 'bu'=>burpees]
    $m = obtener_metas_por_edad($pdo, $edad);
    if (!$m) {
        return [
            'componentes' => [
                'carrera'    => ['porcentaje'=>0.0,'puntaje'=>0.0],
                'velocidad'  => ['porcentaje'=>0.0,'puntaje'=>0.0],
                'abdominales'=> ['porcentaje'=>0.0,'puntaje'=>0.0],
                'lagartijas' => ['porcentaje'=>0.0,'puntaje'=>0.0],
                'barras'     => ['porcentaje'=>0.0,'puntaje'=>0.0],
                'burpees'    => ['porcentaje'=>0.0,'puntaje'=>0.0],
            ],
            'promedio_0_100' => 0.0,
            'promedio_0_10'  => 0.0,
        ];
    }

    $p12  = min(100, ($v['m']  / max(1,$m['meta_12min_m']))   * 100);
    $p100 = min(100, (max(0.01,$m['meta_100m_s']) / max(0.01,$v['s'])) * 100);
    $pabd = min(100, ($v['ab'] / max(1,$m['meta_abdom']))     * 100);
    $plag = min(100, ($v['lg'] / max(1,$m['meta_lagart']))    * 100);
    $pbar = min(100, ($v['br'] / max(1,$m['meta_barras']))    * 100);
    $pbur = min(100, ($v['bu'] / max(1,$m['meta_burpees']))   * 100);

    $prom = ($p12 + $p100 + $pabd + $plag + $pbar + $pbur) / 6.0;

    return [
        'componentes' => [
            'carrera'    => ['porcentaje'=>$p12, 'puntaje'=>round($p12 / 10, 2)],
            'velocidad'  => ['porcentaje'=>$p100,'puntaje'=>round($p100 / 10, 2)],
            'abdominales'=> ['porcentaje'=>$pabd,'puntaje'=>round($pabd / 10, 2)],
            'lagartijas' => ['porcentaje'=>$plag,'puntaje'=>round($plag / 10, 2)],
            'barras'     => ['porcentaje'=>$pbar,'puntaje'=>round($pbar / 10, 2)],
            'burpees'    => ['porcentaje'=>$pbur,'puntaje'=>round($pbur / 10, 2)],
        ],
        'promedio_0_100' => round($prom, 2),
        'promedio_0_10'  => round($prom / 10, 2),
    ];
}

function calcular_calificacion_fisica(PDO $pdo, int $edad, array $v): float {
    $detalles = calcular_componentes_fisica($pdo, $edad, $v);
    return $detalles['promedio_0_100'];
}

function clasificacion_fisica_por_nota(PDO $pdo, float $nota): ?array {
    $sql = "SELECT nota_min, nota_max, etiqueta, compensacion\n            FROM clasificacion_fisica\n            WHERE :nota BETWEEN nota_min AND nota_max\n            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':nota'=>$nota]);
    $r = $st->fetch();
    return $r ?: null;
}
function ultima_cal_fisica_mes(PDO $pdo, string $no_emp, string $mesYmd): ?float {
    $sql = "SELECT fr.cal_fisica
            FROM fisicas_registro fr
            JOIN fisicas_sesion fs ON fs.id = fr.sesion_id
            WHERE fr.no_emp = :no_emp
              AND fs.fecha <= LAST_DAY(:mes)
              AND fr.cal_fisica IS NOT NULL
            ORDER BY fs.fecha DESC
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':no_emp'=>$no_emp, ':mes'=>$mesYmd]);
    $r = $st->fetch();
    return $r['cal_fisica'] ?? null;
}

/** Promedio calificación final de cursos aprobados hasta fin de mes */
function promedio_cursos(PDO $pdo, string $no_emp, string $mesYmd): ?float {
    $sql = "SELECT ROUND(AVG(calificacion_final),2) AS prom
            FROM cursos
            WHERE no_emp = :no_emp AND aprobado = 1 AND (fecha_fin IS NULL OR fecha_fin <= LAST_DAY(:mes))";
    $st = $pdo->prepare($sql);
    $st->execute([':no_emp'=>$no_emp, ':mes'=>$mesYmd]);
    $r = $st->fetch();
    return $r['prom'] ?? null;
}

/** Promedio satisfacción 0–100 del mes por empleado */
function promedio_satisf_mes(PDO $pdo, string $no_emp, string $mesYmd): ?float {
    $sql = "SELECT ROUND(AVG(cal_satisf_0_100),2) AS prom
            FROM encuesta_cliente
            WHERE no_emp = :no_emp
              AND fecha >= DATE_FORMAT(:mes, '%Y-%m-01')
              AND fecha <= LAST_DAY(:mes)";
    $st = $pdo->prepare($sql);
    $st->execute([':no_emp'=>$no_emp, ':mes'=>$mesYmd]);
    $r = $st->fetch();
    return $r['prom'] ?? null;
}

/** Calificación 0–100 de evaluación mensual del mes */
function cal_desempeno_mes(PDO $pdo, string $no_emp, string $mesYmd): ?float {
    $sql = "SELECT cal_desemp_0_100 FROM evaluacion_mensual WHERE no_emp=:no_emp AND mes = DATE_FORMAT(:mes, '%Y-%m-01') LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':no_emp'=>$no_emp, ':mes'=>$mesYmd]);
    $r = $st->fetch();
    return $r['cal_desemp_0_100'] ?? null;
}

/** Faltas del mes (cuentan solo tipo FALTA para alimentación) */
function faltas_del_mes(PDO $pdo, string $no_emp, string $mesYmd): int {
    $sql = "SELECT COUNT(*) AS c
            FROM faltas
            WHERE no_emp = :no_emp
              AND tipo='FALTA'
              AND fecha >= DATE_FORMAT(:mes, '%Y-%m-01')
              AND fecha <= LAST_DAY(:mes)";
    $st = $pdo->prepare($sql);
    $st->execute([':no_emp'=>$no_emp, ':mes'=>$mesYmd]);
    $r = $st->fetch();
    return (int)($r['c'] ?? 0);
}

/** ¿Hay acta vigente en el mes (anula incentivo)? */
function acta_vigente(PDO $pdo, string $no_emp, string $mesYmd): bool {
    $sql = "SELECT 1
            FROM acta_administrativa a
            WHERE a.no_emp = :no_emp
              AND DATE_ADD(a.fecha_acta, INTERVAL a.meses_sancion MONTH) >= LAST_DAY(:mes)
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':no_emp'=>$no_emp, ':mes'=>$mesYmd]);
    return (bool)$st->fetchColumn();
}

/** ¿Tiene autorización de gerente para recibir incentivo antes de 3 meses? */
function tiene_autorizacion(PDO $pdo, string $no_emp, string $mesYmd): bool {
    $sql = "SELECT 1
            FROM autorizacion_incentivo
            WHERE no_emp = :no_emp
              AND mes_desde <= DATE_FORMAT(:mes, '%Y-%m-01')
              AND (mes_hasta IS NULL OR mes_hasta >= LAST_DAY(:mes))
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':no_emp'=>$no_emp, ':mes'=>$mesYmd]);
    return (bool)$st->fetchColumn();
}

/** Antigüedad en meses del empleado al fin del mes */
function antiguedad_meses_al_mes(PDO $pdo, string $no_emp, string $mesYmd): int {
    $st = $pdo->prepare("SELECT fecha_alta FROM empleados WHERE no_emp = :no_emp LIMIT 1");
    $st->execute([':no_emp'=>$no_emp]);
    $r = $st->fetch();
    if (!$r || !$r['fecha_alta']) return 0;
    $finMes = (new DateTime($mesYmd))->format('Y-m-t');
    return meses_entre($r['fecha_alta'], $finMes);
}




