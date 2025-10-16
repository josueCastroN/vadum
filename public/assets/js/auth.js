/* =========================================
   VADUM · Manejo simple de sesión en el frontend
   - Verifica si hay usuario logeado
   - Controla visibilidad de links por rol
   - Implementa botón "Salir"
   ========================================= */

window.USUARIO = null;

/* Llama /auth/me para conocer sesión actual */
async function cargarSesion() {
  try {
    const r = await fetch('../api/index.php?ruta=auth/me', { credentials: 'include' });
    const j = await r.json();
    if (j.ok && j.logeado) {
      window.USUARIO = j.usuario; // {usuario, rol, empleado_no_emp, punto_id}
      aplicarVisibilidadPorRol();
      pintarUsuarioEnHeader();
    } else {
      // si no está logeado y no estamos en login, redirigimos
      if (!location.pathname.endsWith('/login.html')) {
        location.href = './login.html';
      }
    }
  } catch (e) {
    console.error(e);
  }
}

/* Muestra/oculta elementos con data-roles="admin,supervisor"... */
function aplicarVisibilidadPorRol() {
  if (!window.USUARIO) return;
  const rol = window.USUARIO.rol;
  document.querySelectorAll('[data-roles]').forEach(el => {
    const roles = el.getAttribute('data-roles').split(',').map(s => s.trim());
    el.style.display = roles.includes(rol) ? '' : 'none';
  });
}

/* Muestra el usuario y agrega acción al botón Salir si existe */
function pintarUsuarioEnHeader() {
  const span = document.getElementById('usuarioActual');
  if (span && window.USUARIO) {
    span.textContent = `${window.USUARIO.usuario} · ${window.USUARIO.rol}`;
  }
  const salir = document.getElementById('btnSalir');
  if (salir) {
    salir.onclick = async () => {
      await fetch('../api/index.php?ruta=auth/logout', { method:'POST', credentials:'include' });
      location.href = './login.html';
    };
  }
}

/* Arranca chequeo de sesión en carga */
document.addEventListener('DOMContentLoaded', cargarSesion);
