<?php
// Utilidades compartidas para módulos de Evaluación Física

/**
 * Devuelve la clasificación (etiqueta, compensación) para una nota 0–100.
 * Intenta leer de la tabla clasificacion_fisica; si no existe o está vacía,
 * usa una tabla por defecto.
 * @param PDO   $pdo
 * @param float $nota 0..100
 * @return array{etiqueta:string,compensacion:float,nota_min:int,nota_max:int}
 */
function clasificacion_fisica_por_nota(PDO $pdo, float $nota): array {
  $nota = max(0.0, min(100.0, (float)$nota));

  // Intentar obtener desde la BD
  try {
    $st = $pdo->prepare(
      "SELECT etiqueta, compensacion, nota_min, nota_max
         FROM clasificacion_fisica
        WHERE :n BETWEEN nota_min AND nota_max
        ORDER BY nota_min DESC
        LIMIT 1"
    );
    $st->execute([':n'=>$nota]);
    $row = $st->fetch();
    if ($row && isset($row['etiqueta'])) {
      return [
        'etiqueta'      => (string)$row['etiqueta'],
        'compensacion'  => (float)$row['compensacion'],
        'nota_min'      => (int)$row['nota_min'],
        'nota_max'      => (int)$row['nota_max'],
      ];
    }
  } catch (Throwable $e) {
    // Si la tabla no existe o hay error, caemos al default
  }

  // Tabla por defecto
  $defaults = [
    ['min'=>0,  'max'=>59, 'etq'=>'IRREGULAR', 'comp'=>0.00],
    ['min'=>60, 'max'=>69, 'etq'=>'REGULAR',   'comp'=>2.00],
    ['min'=>70, 'max'=>79, 'etq'=>'BIEN',      'comp'=>4.00],
    ['min'=>80, 'max'=>89, 'etq'=>'MUY BIEN',  'comp'=>6.00],
    ['min'=>90, 'max'=>100,'etq'=>'EXCELENTE', 'comp'=>12.00],
  ];
  foreach ($defaults as $r) {
    if ($nota >= $r['min'] && $nota <= $r['max']) {
      return [
        'etiqueta'     => $r['etq'],
        'compensacion' => (float)$r['comp'],
        'nota_min'     => (int)$r['min'],
        'nota_max'     => (int)$r['max'],
      ];
    }
  }
  // Fallback de seguridad
  return [ 'etiqueta'=>'IRREGULAR', 'compensacion'=>0.0, 'nota_min'=>0, 'nota_max'=>59 ];
}


/**
 * Convierte un promedio en escala 1..5 a 0..100.
 * 1 => 0, 5 => 100
 */
function convertir_1a5_a_0a100(float $prom): float {
  return round((($prom - 1.0) / 4.0) * 100.0, 2);
}

/**
 * Convierte un promedio en escala 1..3 a 0..100.
 * 1 => 0, 3 => 100
 */
function convertir_1o3_a_0a100(float $prom): float {
  return round((($prom - 1.0) / 2.0) * 100.0, 2);
}
