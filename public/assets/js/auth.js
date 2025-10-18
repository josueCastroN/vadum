/* =========================================
   VADUM · Sesión, perfil y visibilidad por rol
   ========================================= */

window.USUARIO = null;

/* Carga sesión y aplica UI */
async function cargarSesion() {
  try {
    const r = await fetch('../api/index.php?ruta=auth/me', { credentials:'include' });
    const j = await r.json();

    const esLogin = location.pathname.endsWith('/login.html');
    if (!j.ok || !j.logeado) {
      if (!esLogin) location.href = './login.html';
      return;
    }
    // Si estoy en login y ya estoy logeado, lleve al inicio
    if (esLogin) { location.href = './index.html'; return; }

    window.USUARIO = j.usuario;
    aplicarRolesEnNavegacion();
    poblarPerfil();
    activarMenuPerfil();

  } catch (e) { console.error(e); }
}

/* Muestra/oculta botones por rol usando data-roles */
function aplicarRolesEnNavegacion() {
  const rol = window.USUARIO?.rol;
  document.querySelectorAll('[data-roles]').forEach(el=>{
    const roles = el.getAttribute('data-roles').split(',').map(s=>s.trim());
    el.style.display = roles.includes(rol) ? '' : 'none';
  });
}

/* Perfil visible */
function poblarPerfil() {
  const u = window.USUARIO;
  const span = document.getElementById('usuarioActual');
  const nom  = document.getElementById('perfilNombre');
  const rol  = document.getElementById('perfilRol');
  if (span) span.textContent = u.usuario;
  if (nom)  nom.textContent  = u.usuario;
  if (rol)  rol.textContent  = `Rol: ${u.rol}`;
}

/* Toggle del menú y acciones */
function activarMenuPerfil() {
  const btn = document.getElementById('btnPerfil');
  const dd  = document.getElementById('perfilDropdown');
  const salir = document.getElementById('btnSalir');
  const irPerfil = document.getElementById('irPerfil');

  if (btn && dd) {
    btn.addEventListener('click', (e)=>{ e.stopPropagation(); dd.classList.toggle('activo'); });
    document.addEventListener('click', ()=> dd.classList.remove('activo'));
  }
  if (salir) {
    salir.onclick = async ()=>{
      await fetch('../api/index.php?ruta=auth/logout', { method:'POST', credentials:'include' });
      location.href = './login.html';
    };
  }
  if (irPerfil) {
    irPerfil.onclick = (e)=>{ e.preventDefault(); alert(`Usuario: ${window.USUARIO.usuario}\nRol: ${window.USUARIO.rol}`); };
  }
}

document.addEventListener('DOMContentLoaded', cargarSesion);
