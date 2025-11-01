

(function() {
  'use strict';

  /**
   * Función principal para inicializar el paginador de una tabla.
   * @param {Object} opciones - Objeto de configuración en español.
   * @param {string} opciones.idTabla - ID de la tabla objetivo (sin #).
   * @param {string} opciones.idPaginador - ID del <ul> donde se pintan los botones.
   * @param {string} [opciones.idBuscador] - ID de un <input type="search"> para filtrar.
   * @param {number} [opciones.registrosPorPagina=20] - Límite de filas por página.
   */
  window.inicializarPaginadorTabla = function(opciones) {
    // Validamos opciones básicas
    const idTabla = opciones?.idTabla ?? null;
    const idPaginador = opciones?.idPaginador ?? null;
    const idBuscador = opciones?.idBuscador ?? null;
    const registrosPorPagina = Number(opciones?.registrosPorPagina ?? 20);

    if (!idTabla || !idPaginador) {
      console.error('Paginador: idTabla e idPaginador son obligatorios.');
      return;
    }

    // Obtenemos referencias del DOM
    const tabla = document.getElementById(idTabla);
    const cuerpo = tabla?.querySelector('tbody');
    const paginador = document.getElementById(idPaginador);
    const buscador = idBuscador ? document.getElementById(idBuscador) : null;

    if (!tabla || !cuerpo || !paginador) {
      console.error('Paginador: no se encontró la tabla, tbody o el contenedor del paginador.');
      return;
    }

    // Estado interno en español para claridad
    const estado = {
      paginaActual: 1,
      filasFiltradas: [],  // cache de filas que pasan el filtro
      totalPaginas: 1,
      limite: registrosPorPagina
    };

    // Convertimos las filas HTMLCollection/NodeList a array manipulable
    const filasOriginales = Array.from(cuerpo.querySelectorAll('tr'));

    // -----------------------------------------------------------------------
    // Función: aplicarFiltro
    // Filtra por texto en todas las celdas visibles de la fila.
    // -----------------------------------------------------------------------
    function aplicarFiltro(texto) {
      const termino = (texto || '').trim().toLowerCase();
      if (!termino) {
        estado.filasFiltradas = [...filasOriginales];
        return;
      }
      estado.filasFiltradas = filasOriginales.filter((fila) => {
        const contenido = fila.innerText.toLowerCase();
        return contenido.includes(termino);
      });
    }

    // -----------------------------------------------------------------------
    // Función: calcularPaginas
    // Define el total de páginas en base al filtro y el límite.
    // -----------------------------------------------------------------------
    function calcularPaginas() {
      estado.totalPaginas = Math.max(1, Math.ceil(estado.filasFiltradas.length / estado.limite));
      if (estado.paginaActual > estado.totalPaginas) {
        estado.paginaActual = estado.totalPaginas;
      }
    }

    // -----------------------------------------------------------------------
    // Función: pintarTabla
    // Muestra en el <tbody> únicamente las filas de la página actual.
    // -----------------------------------------------------------------------
    function pintarTabla() {
      // Limpiamos el tbody
      cuerpo.innerHTML = '';

      // Calculamos índice de inicio y fin
      const inicio = (estado.paginaActual - 1) * estado.limite;
      const fin = inicio + estado.limite;

      // Tomamos el "slice" correspondiente
      const slice = estado.filasFiltradas.slice(inicio, fin);

      // Agregamos filas visibles al DOM
      slice.forEach((fila) => cuerpo.appendChild(fila));
    }

    // -----------------------------------------------------------------------
    // Función: pintarPaginador
    // Crea la barrita de paginación Bootstrap (Anterior, páginas, Siguiente).
    // -----------------------------------------------------------------------
    function pintarPaginador() {
      paginador.innerHTML = '';

      // Botón "Anterior"
      const itemAnterior = document.createElement('li');
      itemAnterior.className = `page-item ${estado.paginaActual === 1 ? 'disabled' : ''}`;
      itemAnterior.innerHTML = `<a class="page-link" href="#" aria-label="Anterior">&laquo;</a>`;
      itemAnterior.addEventListener('click', (e) => {
        e.preventDefault();
        if (estado.paginaActual > 1) {
          estado.paginaActual--;
          render();
        }
      });
      paginador.appendChild(itemAnterior);

      // Páginas numeradas (inteligente si hay muchas)
      const maxMostrar = 7; // máximo de botones visibles
      let inicio = Math.max(1, estado.paginaActual - Math.floor(maxMostrar / 2));
      let fin = Math.min(estado.totalPaginas, inicio + maxMostrar - 1);
      if (fin - inicio + 1 < maxMostrar) {
        inicio = Math.max(1, fin - maxMostrar + 1);
      }

      for (let i = inicio; i <= fin; i++) {
        const item = document.createElement('li');
        item.className = `page-item ${i === estado.paginaActual ? 'active' : ''}`;
        item.innerHTML = `<a class="page-link" href="#">${i}</a>`;
        item.addEventListener('click', (e) => {
          e.preventDefault();
          estado.paginaActual = i;
          render();
        });
        paginador.appendChild(item);
      }

      // Botón "Siguiente"
      const itemSiguiente = document.createElement('li');
      itemSiguiente.className = `page-item ${estado.paginaActual === estado.totalPaginas ? 'disabled' : ''}`;
      itemSiguiente.innerHTML = `<a class="page-link" href="#" aria-label="Siguiente">&raquo;</a>`;
      itemSiguiente.addEventListener('click', (e) => {
        e.preventDefault();
        if (estado.paginaActual < estado.totalPaginas) {
          estado.paginaActual++;
          render();
        }
      });
      paginador.appendChild(itemSiguiente);
    }

    // -----------------------------------------------------------------------
    // Función: render
    // Orquesta el repintado completo (tabla + paginador).
    // -----------------------------------------------------------------------
    function render() {
      calcularPaginas();
      pintarTabla();
      pintarPaginador();
    }

    // -----------------------------------------------------------------------
    // Inicialización
    // -----------------------------------------------------------------------
    aplicarFiltro(''); // sin filtro al inicio
    render();

    // Si hay buscador, escuchamos cambios para filtrar en vivo
    if (buscador) {
      buscador.addEventListener('input', (e) => {
        estado.paginaActual = 1;        // siempre regresamos a la página 1
        aplicarFiltro(e.target.value);   // aplicamos el filtro
        render();                        // repintamos
      });
    }

    // Exponemos un método opcional por si quieres cambiar el límite desde otro lugar
    tabla.cambiarLimite = function(nuevoLimite) {
      const n = Number(nuevoLimite);
      if (!isNaN(n) && n > 0) {
        estado.limite = n;
        estado.paginaActual = 1;
        render();
      }
    };
  };
})();
