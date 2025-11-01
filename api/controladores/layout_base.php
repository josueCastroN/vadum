<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>VADUM | Panel</title>

  <!-- Bootstrap 5 -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">

  <style>
    /* Altura completa para permitir sticky en sidebar */
    html, body {
      height: 100%;
      background: #f3f3f3;
    }

    /* Contenedor principal */
    .contenedor-app {
      min-height: 100vh;
    }

    /* Sidebar: pegajoso (sticky) con scroll propio si es largo */
    .sidebar {
      position: sticky;
      top: 0;                 /* se pega al top del viewport */
      height: 100vh;          /* ocupa toda la altura de la ventana */
      overflow-y: auto;       /* scroll interno si el menú crece */
      background: #2e2e2e;    /* gris institucional */
      color: #f3f3f3;
      padding: 16px;
      border-right: 1px solid rgba(255,255,255,.08);
    }

    .sidebar .logo-compacto {
      height: 36px;
      width: auto;
      object-fit: contain;
      margin-right: 8px;
    }

    /* Enlaces del menú */
    .menu-link {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: #f3f3f3;
      padding: 10px 12px;
      border-radius: 10px;
      transition: background .2s ease;
      font-weight: 500;
    }
    .menu-link:hover,
    .menu-link.activo {
      background: #1f1f1f;
      color: #fdda25;
    }

    /* Encabezado del contenido */
    .encabezado-contenido {
      background: white;
      border-bottom: 1px solid #e7e7e7;
      padding: 16px 20px;
      position: sticky;
      top: 0;
      z-index: 5;
    }

    /* Tarjetas y tablas */
    .tarjeta {
      border: none;
      border-radius: 14px;
      box-shadow: 0 6px 20px rgba(0,0,0,.06);
    }
  </style>
</head>
<body>
  <!-- Barra superior para móvil con botón de menú -->
  <nav class="navbar navbar-dark bg-dark d-md-none">
    <div class="container-fluid">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#">
        <img src="assets/img/logo_vadum.png" class="logo-compacto" alt="VADUM">
        <span>VADUM</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>
  </nav>

  <!-- Menú lateral como offcanvas en móvil -->
  <div class="offcanvas offcanvas-start d-md-none text-bg-dark" tabindex="-1" id="offcanvasMenu">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title">Menú</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
      <!-- Mismo contenido del sidebar -->
      <nav class="d-flex flex-column gap-1">
        <a class="menu-link" href="dashboard.php">🏠 Dashboard</a>
        <a class="menu-link" href="empleados.php">👤 Empleados</a>
        <a class="menu-link" href="evaluaciones_fisicas.php">💪 Eval. Físicas</a>
        <a class="menu-link" href="evaluaciones_supervisor.php">📝 Eval. Supervisor</a>
        <a class="menu-link" href="evaluaciones_cliente.php">⭐ Eval. Cliente</a>
        <a class="menu-link" href="cursos.php">🎓 Cursos</a>
        <a class="menu-link" href="faltas.php">⏰ Faltas</a>
        <hr class="border-secondary">
        <a class="menu-link" href="cerrar_sesion.php">🚪 Cerrar sesión</a>
      </nav>
    </div>
  </div>

  <!-- Layout principal: sidebar (md+) + contenido -->
  <div class="container-fluid contenedor-app">
    <div class="row">
      <!-- Sidebar fijo (visible en md y superiores) -->
      <aside class="col-md-3 col-lg-2 d-none d-md-block sidebar">
        <div class="d-flex align-items-center mb-3">
          <img src="assets/img/logo_vadum.png" class="logo-compacto" alt="VADUM">
          <strong>Sistema VADUM</strong>
        </div>

        <nav class="d-flex flex-column gap-1">
          <!-- Agrega la clase .activo según la página actual -->
          <a class="menu-link activo" href="dashboard.php">🏠 Dashboard</a>
          <a class="menu-link" href="empleados.php">👤 Empleados</a>
          <a class="menu-link" href="evaluaciones_fisicas.php">💪 Eval. Físicas</a>
          <a class="menu-link" href="evaluaciones_supervisor.php">📝 Eval. Supervisor</a>
          <a class="menu-link" href="evaluaciones_cliente.php">⭐ Eval. Cliente</a>
          <a class="menu-link" href="cursos.php">🎓 Cursos</a>
          <a class="menu-link" href="faltas.php">⏰ Faltas</a>
          <hr class="border-secondary">
          <a class="menu-link" href="cerrar_sesion.php">🚪 Cerrar sesión</a>
        </nav>
      </aside>

      <!-- Contenido -->
      <main class="col-md-9 col-lg-10 p-0">
        <div class="encabezado-contenido">
          <h1 class="h5 m-0">Título de la pantalla</h1>
          <small class="text-muted">Descripción breve de la sección</small>
        </div>

        <div class="container py-4">
          <!-- Ejemplo de tarjeta con tabla paginada (ver sección 3) -->
          <div class="card tarjeta mb-4">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 m-0">Registros</h2>
                <input type="search" class="form-control form-control-sm" placeholder="Buscar..." id="buscadorTablaDemo" style="max-width: 260px;">
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle tabla-paginada" id="tablaDemo">
                  <thead class="table-dark">
                    <tr>
                      <th>#</th>
                      <th>Nombre</th>
                      <th>Puesto</th>
                      <th>Región</th>
                    </tr>
                  </thead>
                  <tbody>
                    <!-- DEMO: 60 filas para mostrar la paginación -->
                    <!-- En producción, estas filas se imprimen con PHP desde tu consulta -->
                    <?php for ($i=1; $i<=60; $i++): ?>
                      <tr>
                        <td><?php echo $i; ?></td>
                        <td>Empleado <?php echo $i; ?></td>
                        <td>Vigilante</td>
                        <td>Pacífico</td>
                      </tr>
                    <?php endfor; ?>
                  </tbody>
                </table>
              </div>

              <!-- Contenedor donde se pintan los controles de paginación -->
              <nav aria-label="Paginación de tabla">
                <ul class="pagination pagination-sm mb-0" id="paginador_tablaDemo"></ul>
              </nav>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Paginación y utilidades (sección 3) -->
  <script src="assets/js/paginador_tablas.js"></script>

  <!-- Inicialización de la tabla de ejemplo -->
  <script>
    // Inicializamos el paginador para la tabla de ejemplo
    document.addEventListener('DOMContentLoaded', () => {
      // Configuración en español y 20 registros por página
      inicializarPaginadorTabla({
        idTabla: 'tablaDemo',
        idPaginador: 'paginador_tablaDemo',
        idBuscador: 'buscadorTablaDemo',
        registrosPorPagina: 20
      });
    });
  </script>
</body>
</html>
