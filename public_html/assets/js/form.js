/* Carrera 7K — Formulario de pre-inscripción
 * Validación cliente + CSRF + envío al backend PHP. */
(function () {
    'use strict';

    const form = document.getElementById('registrationForm');
    if (!form) return;

    const submitBtn = document.getElementById('submitBtn');
    const acceptTerms = document.getElementById('acceptTerms');
    
    if (acceptTerms && submitBtn) {
        acceptTerms.addEventListener('change', () => {
            if (acceptTerms.checked) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    }

    const CSRF_ENDPOINT = 'api/csrf-token.php';
    const SUBMIT_ENDPOINT = 'api/inscripcion.php';

    // -------------------------------------------------------------------------
    // Obtener token CSRF al cargar la página
    // -------------------------------------------------------------------------
    async function fetchCsrfToken() {
        try {
            const res = await fetch(CSRF_ENDPOINT, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('CSRF fetch failed');
            const data = await res.json();
            const input = form.querySelector('input[name="csrf_token"]');
            if (input) input.value = data.token || '';
        } catch (err) {
            console.warn('No se pudo obtener token CSRF:', err);
        }
    }
    fetchCsrfToken();

    // -------------------------------------------------------------------------
    // Validadores puntuales
    // -------------------------------------------------------------------------
    const validators = {
        dni:  v => /^\d{8}$/.test(v),
        cel:  v => /^9\d{8}$/.test(v),
        edad: v => {
            const n = parseInt(v, 10);
            return !isNaN(n) && n >= 14 && n <= 100;
        },
        nombres: v => v.trim().length >= 5 && v.trim().length <= 120,
        texto:   v => v.trim().length >= 2 && v.trim().length <= 100
    };

    function setFieldError(input, msg) {
        const wrap = input.closest('div');
        if (!wrap) return;
        let err = wrap.querySelector('.form-error');
        if (!err) {
            err = document.createElement('span');
            err.className = 'form-error';
            wrap.appendChild(err);
        }
        err.textContent = msg || '';
        input.setAttribute('aria-invalid', msg ? 'true' : 'false');
    }

    function clearAllErrors() {
        form.querySelectorAll('.form-error').forEach(e => e.textContent = '');
        form.querySelectorAll('[aria-invalid="true"]').forEach(e => e.removeAttribute('aria-invalid'));
    }

    // -------------------------------------------------------------------------
    // Validación completa antes de enviar
    // -------------------------------------------------------------------------
    function validateForm(fd) {
        const errors = [];
        const check = (name, ok, msg) => { if (!ok) errors.push({ name, msg }); };

        check('nombres_completos', validators.nombres(fd.get('nombres_completos') || ''),
              'Ingrese apellidos y nombres (mínimo 5 caracteres).');
        check('dni', validators.dni(fd.get('dni') || ''), 'DNI debe tener 8 dígitos.');
        check('edad', validators.edad(fd.get('edad') || ''), 'Edad entre 14 y 100.');
        check('celular', validators.cel(fd.get('celular') || ''),
              'Celular debe empezar con 9 y tener 9 dígitos.');
        check('distrito', validators.texto(fd.get('distrito') || ''), 'Distrito requerido.');
        check('provincia', validators.texto(fd.get('provincia') || ''), 'Provincia requerida.');
        check('departamento', validators.texto(fd.get('departamento') || ''),
              'Departamento requerido.');
        check('categoria', !!fd.get('categoria'), 'Seleccione una categoría.');

        // Si es menor de edad (< 18), los datos del apoderado son obligatorios
        const edad = parseInt(fd.get('edad') || '0', 10);
        if (edad > 0 && edad < 18) {
            check('apoderado_nombres',
                  validators.nombres(fd.get('apoderado_nombres') || ''),
                  'Datos del apoderado obligatorios para menores.');
            check('apoderado_dni', validators.dni(fd.get('apoderado_dni') || ''),
                  'DNI del apoderado (8 dígitos).');
            check('apoderado_celular', validators.cel(fd.get('apoderado_celular') || ''),
                  'Celular del apoderado (9 dígitos, empieza con 9).');
        }

        // Aceptar términos
        if (fd.get('accept_terms') !== 'on') {
            errors.push({ name: 'accept_terms', msg: 'Debe aceptar la declaración jurada.' });
        }

        // Categoría vs edad (sanity check en cliente, backend re-valida)
        const cat = fd.get('categoria') || '';
        if (edad && cat) {
            if (cat.startsWith('juvenil') && (edad < 14 || edad > 17)) {
                errors.push({ name: 'categoria', msg: 'Categoría Juvenil es 14-17 años.' });
            } else if (cat.startsWith('libre') && (edad < 18 || edad > 39)) {
                errors.push({ name: 'categoria', msg: 'Categoría Libre es 18-39 años.' });
            } else if (cat.startsWith('master') && edad < 40) {
                errors.push({ name: 'categoria', msg: 'Categoría Máster es 40+ años.' });
            }
        }

        return errors;
    }

    // -------------------------------------------------------------------------
    // Submit
    // -------------------------------------------------------------------------
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearAllErrors();

        const submitBtn = form.querySelector('button[type="submit"]');
        const fd = new FormData(form);

        // Honeypot: si tiene contenido, es bot — silenciosamente "aceptar" sin enviar
        if ((fd.get('website') || '').trim() !== '') {
            window.showToast('¡Pre-inscripción enviada!', 'success');
            form.reset();
            return;
        }

        const errors = validateForm(fd);
        if (errors.length) {
            errors.forEach(({ name, msg }) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (el) setFieldError(el, msg);
            });
            window.showToast(errors[0].msg, 'warning');
            const first = form.querySelector(`[name="${errors[0].name}"]`);
            if (first && first.focus) first.focus();
            return;
        }

        submitBtn.disabled = true;
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span>Enviando…</span>';

        try {
            const res = await fetch(SUBMIT_ENDPOINT, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: fd
            });

            let data = {};
            try { data = await res.json(); } catch (_) { /* respuesta no JSON */ }

            if (res.ok && data.success) {
                window.showToast(
                    data.message || '¡Pre-inscripción registrada! Confirme presencialmente en Protalento.',
                    'success'
                );
                form.reset();
                fetchCsrfToken(); // renovar token después de éxito
            } else if (res.status === 429) {
                window.showToast(
                    data.message || 'Demasiados intentos. Espere unos minutos antes de reintentar.',
                    'warning'
                );
            } else if (res.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([name, msg]) => {
                    const el = form.querySelector(`[name="${name}"]`);
                    if (el) setFieldError(el, msg);
                });
                window.showToast(data.message || 'Revise los campos marcados.', 'warning');
            } else if (res.status === 409) {
                window.showToast(
                    data.message || 'Ya existe una inscripción con ese DNI.',
                    'warning'
                );
            } else {
                window.showToast(
                    data.message || 'Ocurrió un error. Intente nuevamente.',
                    'error'
                );
            }
        } catch (err) {
            console.error(err);
            window.showToast('Error de conexión. Verifique su internet.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    });

    // -------------------------------------------------------------------------
    // UX: solo dígitos en DNI/celular/edad, uppercase automático en nombres
    // -------------------------------------------------------------------------
    form.querySelectorAll('input[data-only-digits]').forEach(inp => {
        inp.addEventListener('input', () => {
            inp.value = inp.value.replace(/\D+/g, '');
        });
    });
})();
