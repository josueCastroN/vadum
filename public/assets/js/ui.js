

(function () {
  'use strict';

  // --------------------------------------------------------------------------
  // Función: marcarActivoSegunURL
  // Marca como "activo" el enlace del menú lateral que coincida con la URL.
  // --------------------------------------------------------------------------
  function marcarActivoSegunURL() {
    try {
      const enlaces = document.querySelectorAll('#menuLateral .menu-link');
      const paginaActual = location.pathname.split('/').pop() || 'index.php';

      enlaces.forEach((a) => {
        a.classList.remove('activo');
        const href = a.getAttribute('href') || '';
        if (href === paginaActual) a.classList.add('activo');
      });
    } catch (e) {
      console.warn('UI: no se pudo marcar el menú activo:', e);
    }
  }

  // --------------------------------------------------------------------------
  // Función: inicializarPaginadoresAuto
  // Busca tablas que usen data-atributos: data-paginador, data-buscador, data-registros.
  // Llama al paginador base (paginador_tablas.js) para cada una.
  // --------------------------------------------------------------------------
  function inicializarPaginadoresAuto() {
    if (typeof window.inicializarPaginadorTabla !== 'function') {
      console.warn('UI: paginador_tablas.js no está cargado.');
      return;
    }

    const tablas = document.querySelectorAll('table.tabla-paginada[data-paginador]');
    tablas.forEach((tabla) => {
      const idTabla = tabla.getAttribute('id');
      const idPaginador = tabla.getAttribute('data-paginador');
      const idBuscador = tabla.getAttribute('data-buscador') || null;
      const registros = Number(tabla.getAttribute('data-registros') || 20);

      if (!idTabla) {
        console.warn('UI: una tabla paginada no tiene atributo id.');
        return;
      }

      inicializarPaginadorTabla({
        idTabla,
        idPaginador,
        idBuscador,
        registrosPorPagina: registros
      });
    });
  }

  // --------------------------------------------------------------------------
  // Helpers opcionales: toast y overlay de carga
  // --------------------------------------------------------------------------
  function mostrarToast(mensaje = 'Operación realizada', tiempoMs = 2500) {
    try {
      // Creamos contenedor si no existe
      let contenedor = document.getElementById('contenedorToasts');
      if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'contenedorToasts';
        contenedor.className = 'position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(contenedor);
      }

      // Toast básico Bootstrap
      const wrapper = document.createElement('div');
      wrapper.className = 'toast align-items-center text-bg-dark border-0';
      wrapper.role = 'alert';
      wrapper.ariaLive = 'assertive';
      wrapper.ariaAtomic = 'true';

      wrapper.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">
            ${mensaje}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      `;

      contenedor.appendChild(wrapper);
      const toast = new bootstrap.Toast(wrapper, { delay: tiempoMs });
      toast.show();

      // Remover al ocultar
      wrapper.addEventListener('hidden.bs.toast', () => wrapper.remove());
    } catch (e) {
      console.warn('UI: no se pudo mostrar el toast:', e);
      alert(mensaje); // fallback simple
    }
  }

  function mostrarOverlayCarga(mostrar = true, texto = 'Cargando...') {
    let overlay = document.getElementById('overlayCarga');

    if (mostrar) {
      if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'overlayCarga';
        overlay.className = 'overlay-carga d-flex align-items-center justify-content-center';
        overlay.innerHTML = `
          <div class="tarjeta-carga text-center">
            <div class="spinner-border" role="status"></div>
            <div class="mt-2">${texto}</div>
          </div>
        `;
        document.body.appendChild(overlay);
      }
    } else if (overlay) {
      overlay.remove();
    }
  }

  // Exponemos helpers por si los quieres usar en otros scripts
  window.VADUM_UI = {
    marcarActivoSegunURL,
    inicializarPaginadoresAuto,
    mostrarToast,
    mostrarOverlayCarga
  };

  // Arranque automático cuando el DOM esté listo
  document.addEventListener('DOMContentLoaded', () => {
    marcarActivoSegunURL();
    inicializarPaginadoresAuto();
  });
})();
