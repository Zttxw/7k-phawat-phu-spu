# Carrera 7K "Phaway Phu'spu" — San Jerónimo 2026

Sistema de pre-inscripción con landing page, backend PHP y base de datos en Excel (`.xlsx`).
Preparado para desplegarse en un hosting compartido con **cPanel**.

---

## 📁 Estructura del proyecto

```
maraton-7k/
├── public_html/                  ← Contenido web (accesible por navegador)
│   ├── index.html                ← Landing page principal
│   ├── .htaccess                 ← Headers de seguridad + rewrites
│   ├── assets/
│   │   ├── css/styles.css
│   │   ├── js/
│   │   │   ├── main.js           ← countdown, navbar, FAQ, toast
│   │   │   └── form.js           ← validación + CSRF + envío
│   │   └── img/                  ← logo.png, mapa-recorrido.png
│   ├── api/
│   │   ├── csrf-token.php        ← GET: emite token CSRF
│   │   └── inscripcion.php       ← POST: recibe formulario
│   └── admin/
│       ├── login.php             ← autenticación
│       ├── index.php             ← listado + búsqueda
│       ├── descargar.php         ← descarga del .xlsx
│       ├── logout.php
│       └── _auth.php             ← guard de sesión
│
├── includes/                     ← NO accesible por web
│   ├── config.php                ← rutas, credenciales admin, límites
│   ├── security.php              ← CSRF, rate-limit, sanitización
│   ├── excel-handler.php         ← lectura/escritura del Excel (con lock)
│   └── .htaccess                 ← Require all denied
│
├── storage/                      ← NO accesible por web
│   ├── inscripciones.xlsx        ← DATOS (se crea al primer registro)
│   ├── rate_limit.json           ← estado del rate limiter
│   ├── logs/                     ← logs de seguridad y errores
│   └── .htaccess                 ← Require all denied
│
├── vendor/                       ← Dependencias de Composer (se genera)
├── composer.json
└── README.md
```

---

## 🚀 Despliegue en cPanel — Paso a paso

### 1. Preparar credenciales del admin

Antes de subir, genera un **hash de contraseña** para el panel admin. En cualquier PC con PHP:

```bash
php -r "echo password_hash('TU_CONTRASEÑA_SEGURA_AQUI', PASSWORD_DEFAULT), PHP_EOL;"
```

Copia el resultado (empieza con `$2y$10$…`) y pégalo en [includes/config.php](includes/config.php:37) reemplazando el valor de `ADMIN_PASS_HASH`. Cambia también `ADMIN_USER` si quieres.

### 2. Ajustar `ALLOWED_ORIGINS`

En [includes/config.php](includes/config.php:52) cambia:

```php
define('ALLOWED_ORIGINS', [
    'https://tu-dominio.com',
    'https://www.tu-dominio.com',
]);
```

Por el dominio real que usarás en producción.

### 3. Subir archivos a cPanel

Usando **File Manager** o **FTP**, la estructura en el servidor debe quedar así:

```
/home/USUARIO/                       ← Home de tu cuenta cPanel
├── public_html/                     ← Ya existe (DocumentRoot)
│   └── (contenido de tu public_html/ local)
├── includes/                        ← Súbelo AL MISMO NIVEL que public_html
├── storage/                         ← Súbelo AL MISMO NIVEL que public_html
├── composer.json
└── vendor/                          ← Se generará con composer install
```

> **⚠️ CRÍTICO:** `includes/` y `storage/` NO deben estar dentro de `public_html/`.
> Si tu hosting no te deja subir carpetas fuera de `public_html`, avísame y adapto los paths, pero la seguridad se degrada.

### 4. Instalar dependencias (PhpSpreadsheet)

**Opción A — Terminal SSH del cPanel** (si está habilitada):
```bash
cd /home/USUARIO/
composer install --no-dev --optimize-autoloader
```

**Opción B — Sin SSH:**
1. En tu PC local, dentro de la carpeta del proyecto, ejecuta:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
2. Sube la carpeta `vendor/` completa por FTP al mismo nivel que `public_html/`.

### 5. Permisos de carpetas y archivos

En cPanel File Manager → clic derecho → "Change Permissions":

| Ruta                        | Permisos | Notas                                    |
|-----------------------------|----------|------------------------------------------|
| `public_html/`              | `755`    | por defecto                              |
| `public_html/**/*.php`      | `644`    | por defecto                              |
| `includes/`                 | `750`    | solo dueño puede leer                    |
| `includes/*.php`            | `640`    |                                          |
| `storage/`                  | `750`    | PHP debe poder escribir aquí             |
| `storage/logs/`             | `750`    |                                          |

Si PHP no puede escribir en `storage/`, cambia a `755` (para directorio) / `664` (para archivos).

### 6. Verificar zona horaria y PHP

En cPanel → **Select PHP Version**:
- Versión PHP: **8.0 o superior**
- Extensiones requeridas: `zip`, `xml`, `gd`, `mbstring`, `openssl` (todas activas por defecto)

### 7. Colocar imágenes locales

Descarga las imágenes que actualmente vienen del CDN externo y súbelas a `public_html/assets/img/`:
- `logo.png` — logo de la carrera
- `mapa-recorrido.png` — mapa del recorrido

Ya están referenciadas en el HTML con esos nombres.

### 8. Probar el sitio

1. Abre `https://tu-dominio.com/` → debe cargar la landing.
2. Rellena el formulario de pre-inscripción y envíalo.
3. Ingresa a `https://tu-dominio.com/admin/login.php` con las credenciales que configuraste.
4. Deberías ver la inscripción listada y poder descargar el Excel.

---

## 🔒 Seguridad implementada

| Capa | Detalle |
|------|---------|
| **HTTPS forzado** | Redirect 301 vía `.htaccess` |
| **Headers de seguridad** | HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy |
| **CSRF tokens** | Rotan tras cada envío, TTL 1h, comparación con `hash_equals` |
| **Rate limiting** | 5 envíos/hora por IP (form y login por separado) |
| **Honeypot** | Campo oculto `website` — si se llena, se descarta silenciosamente |
| **Validación server-side** | DNI, celular, edad, categoría vs edad, longitudes, charset UTF-8 |
| **Anti-duplicados** | Se rechaza si el DNI ya está registrado |
| **Verificación de origen** | `Origin` y `Referer` contrastados con `ALLOWED_ORIGINS` |
| **Sesiones** | Cookie HttpOnly + Secure + SameSite=Strict, regeneración de ID en login |
| **Contraseña admin** | `password_hash` (bcrypt) + `password_verify` en tiempo constante |
| **File locking** | `flock()` exclusivo al escribir el Excel para evitar corrupción concurrente |
| **Excel fuera del webroot** | `/storage/` no es alcanzable por URL |
| **Logs de auditoría** | Eventos de seguridad guardados en `storage/logs/security.log` |
| **Anti-directory-listing** | `Options -Indexes` |

---

## 🛠 Desarrollo local

Con PHP 8+ instalado:

```bash
composer install
# Crear config con credenciales de prueba
# Editar includes/config.php: descomentar http://localhost en ALLOWED_ORIGINS

# Servir con el servidor built-in de PHP:
php -S localhost:8000 -t public_html/
```

Visitar `http://localhost:8000/`.

Para generar el hash de admin de prueba:
```bash
php -r "echo password_hash('admin123', PASSWORD_DEFAULT), PHP_EOL;"
```

---

## 📊 El archivo Excel

- Ruta: `storage/inscripciones.xlsx`
- Se **crea automáticamente** al primer registro con cabeceras estilizadas.
- Columnas: ID, Fecha de Registro, Apellidos y Nombres, DNI, Edad, Celular, Domicilio, Distrito, Provincia, Departamento, Categoría, Nombre Apoderado, DNI Apoderado, Celular Apoderado, IP.
- Se protege con `flock()` en cada lectura/escritura.

### Backups recomendados

En cPanel → **Cron Jobs** configura un cron diario que copie el archivo:

```bash
0 2 * * * cp /home/USUARIO/storage/inscripciones.xlsx /home/USUARIO/storage/backups/inscripciones_$(date +\%Y\%m\%d).xlsx
```

---

## ⚙️ Optimizaciones aplicadas

- **CSS/JS externos** con `defer` → no bloquean el parseo del HTML.
- **Preconnect** a Google Fonts.
- **`loading="lazy"`** en imágenes below-the-fold.
- **IntersectionObserver** para scroll reveal (mejor que listener `scroll`).
- **Compresión gzip** en `.htaccess`.
- **Cache-Control** de 30-60 días para estáticos.
- **`prefers-reduced-motion`** respetado (accesibilidad).
- Removido el `<style>` y `<script>` monolíticos de 500 líneas del HTML original.

⚠ **Nota sobre Tailwind CDN**: Para producción real, deberías compilar Tailwind localmente (`npx tailwindcss -o styles.min.css`) en vez de usar el CDN. El CDN es cómodo para prototipos, pero se descarga 3+ MB de JS que compila los estilos en el cliente.

---

## 📝 TODO / Mejoras futuras

- [ ] Compilar Tailwind localmente (eliminar CDN)
- [ ] Descargar íconos de Iconify usados como SVG local
- [ ] Sistema de correos de confirmación (PHPMailer)
- [ ] Panel admin: paginación cuando haya >200 inscritos
- [ ] Exportar filtrado (solo lo que hay en pantalla)
- [ ] Backup automático diario a Google Drive/Dropbox
- [ ] Logs con rotación automática

---

## 📄 Licencia

Propiedad de la Municipalidad Distrital de San Jerónimo — Cusco, Perú.
