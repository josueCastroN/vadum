

document.addEventListener('DOMContentLoaded', () => {
    // Elementos comunes
    const sidebarMenu = document.getElementById('sidebarMenu');
    const offcanvasMenu = document.getElementById('menuOffcanvas');
    const nombreUsuarioSpan = document.getElementById('nombreUsuario');
    const btnCerrarSesionTop = document.getElementById('btnCerrarSesionTop'); const btnCerrarSesionDropdown = document.getElementById('btnCerrarSesionDropdown'); const btnSalirPerfil = document.getElementById('btnSalir');

    // Elementos del login
    const formLogin = document.getElementById('formLogin');
    const campoUsuario = document.getElementById('campoUsuario');
    const campoContrasena = document.getElementById('campoContrasena');
    const btnTogglePass = document.getElementById('btnTogglePass');
    const btnEntrar = document.getElementById('btnEntrar');
    const alertaMensaje = document.getElementById('alertaMensaje');
    const eyeIcon = document.getElementById('eyeIcon');

    const mostrarAlerta = (mensaje, tipo = 'danger') => {
        if (!alertaMensaje) return;
        alertaMensaje.textContent = mensaje;
        alertaMensaje.className = `alert alert-${tipo}`;
        alertaMensaje.classList.remove('d-none');
    };

    async function llamadaAuth(url, opciones = {}) {
        const resp = await fetch(url, {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                ...(opciones.headers || {})
            },
            ...opciones
        });

        const data = await resp.json().catch(() => ({}));
        if (!resp.ok) {
            throw new Error(data.error || 'Error de autenticación');
        }
        return data;
    }

    function aplicarRestriccionesDeRol(usuario) {
        const rol = usuario?.rol || '';
        const nombre = usuario?.nombre || usuario?.usuario || 'Usuario';

        if (nombreUsuarioSpan) {
            nombreUsuarioSpan.textContent = nombre;
            localStorage.setItem('userName', nombre);
        }

        const menus = [sidebarMenu, offcanvasMenu].filter(Boolean);
        menus.forEach(menu => {
            menu.querySelectorAll('a[data-roles]').forEach(enlace => {
                const listaRoles = (enlace.dataset.roles || '')
                    .split(',')
                    .map(r => r.trim())
                    .filter(Boolean);

                enlace.style.display =
                    listaRoles.length === 0 || listaRoles.includes(rol)
                        ? 'flex'
                        : 'none';
            });
        });
    }

    async function verificarSesion() {
        try {
            const respuesta = await llamadaAuth('../api/index.php?ruta=auth/me');
            if (!respuesta.logeado) {
                const actual = location.pathname.split('/').pop();
                if (actual !== 'login.html') {
                    location.href =
                        'login.html?redirect=' +
                        encodeURIComponent(location.pathname);
                }
                return null;
            }
            aplicarRestriccionesDeRol(respuesta.usuario);
            return respuesta.usuario;
        } catch (err) {
            console.error(err);
            const actual = location.pathname.split('/').pop();
            if (actual !== 'login.html') {
                location.href =
                    'login.html?redirect=' +
                    encodeURIComponent(location.pathname);
            }
            return null;
        }
    }

    // Mostrar / ocultar contraseña
    if (btnTogglePass && campoContrasena && eyeIcon) {
        btnTogglePass.addEventListener('click', e => {
            e.preventDefault();
            const mostrar = campoContrasena.type === 'password';
            campoContrasena.type = mostrar ? 'text' : 'password';
            eyeIcon.textContent = mostrar ? 'visibility' : 'visibility_off';
        });
    }

    // Manejo del login
    if (formLogin) {
        formLogin.addEventListener('submit', async e => {
            e.preventDefault();
            mostrarAlerta('', 'd-none');

            const usuario = campoUsuario.value.trim();
            const contrasena = campoContrasena.value.trim();

            if (!usuario || !contrasena) {
                mostrarAlerta('Ingresa tu usuario y contraseña.');
                return;
            }

            btnEntrar.disabled = true;
            btnEntrar.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Validando…';

            try {
                const respuesta = await llamadaAuth(
                    '../api/index.php?ruta=auth/login',
                    {
                        method: 'POST',
                        body: JSON.stringify({
                            usuario,
                            contrasena,
                            recordar: 0
                        })
                    }
                );
                aplicarRestriccionesDeRol(respuesta.usuario);

                const redirect =
                    new URLSearchParams(location.search).get('redirect') ||
                    './index.html';
                window.location.href = redirect;
            } catch (err) {
                console.error(err);
                mostrarAlerta(err.message || 'Credenciales incorrectas.');
            } finally {
                btnEntrar.disabled = false;
                btnEntrar.innerHTML =
                    '<i class="material-icons-outlined me-2">login</i> Entrar';
            }
        });
    } else {
        // Páginas protegidas
        verificarSesion();
    }

    if (btnCerrarSesionTop) {
        btnCerrarSesionTop.addEventListener('click', async e => {
            e.preventDefault();
            try {
                await llamadaAuth('../api/index.php?ruta=auth/logout', {
                    method: 'POST'
                });
            } catch (err) {
                console.warn('Error cerrando sesión', err);
            } finally {
                localStorage.removeItem('userName');
                window.location.href = 'login.html';
            }
        });
    }
});

// Unificaci�n de cabecera/men�s: listeners extra y sincronizaci�n de nombre
document.addEventListener('DOMContentLoaded', () => {
  try {
    const nombre = localStorage.getItem('userName');
    if (nombre) {
      const perfilNombre = document.getElementById('perfilNombre');
      const usuarioActual = document.getElementById('usuarioActual');
      if (perfilNombre) perfilNombre.textContent = nombre;
      if (usuarioActual) usuarioActual.textContent = nombre;
    }
    const doLogout = async (e) => {
      if (e && e.preventDefault) e.preventDefault();
      try { await fetch('../api/index.php?ruta=auth/logout', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'} }); } catch(_) {}
      finally { localStorage.removeItem('userName'); window.location.href = 'login.html'; }
    };
    const dd = document.getElementById('btnCerrarSesionDropdown');
    if (dd) dd.addEventListener('click', doLogout);
    const salir = document.getElementById('btnSalir');
    if (salir) salir.addEventListener('click', doLogout);
  } catch (e) { console.warn('Cabecera unificada: listeners extra no aplicados', e); }
});
