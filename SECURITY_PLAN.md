# Plan de Corrección de Seguridad — Ghost PHP Framework

> Generado: 2026-02-21
> Estado general: 🔴 En progreso

---

## Orden de ejecución

| # | Vulnerabilidad | Severidad | Estado |
|---|---------------|-----------|--------|
| 1 | SQL Injection — valores sin escapar | 🔴 Crítico | ✅ Resuelto |
| 2 | Inyección de nombre de tabla/columna | 🔴 Crítico | ✅ Resuelto |
| 3 | Credenciales expuestas en error de conexión | 🟠 Alto | ⬜ Pendiente |
| 4 | Upload sin validación de tipo de archivo | 🟠 Alto | ⬜ Pendiente |
| 5 | CORS completamente abierto (`*`) | 🟠 Alto | ⬜ Pendiente |
| 6 | Comparación laxa `== NULL` en validación | 🟡 Medio | ⬜ Pendiente |
| 7 | Bug lógico en `validator()` — falla con <3 reglas | 🟡 Medio | ⬜ Pendiente |
| 8 | `get_connect()` usa variables no definidas | 🔵 Bajo | ⬜ Pendiente |
| 9 | Fallback `random_string()` no criptográfico | 🔵 Bajo | ⬜ Pendiente |

---

## Detalle de cada issue

---

### ✅ Issue #1 — SQL Injection (valores sin escapar)
**Severidad:** 🔴 Crítico
**Estado:** ✅ Resuelto — 2026-02-21
**Archivos:** `ghost.php`
**Líneas afectadas:** 484, 533, 735, 749, 818

**Descripción:**
Los valores que vienen del usuario se concatenan directamente en las queries SQL sin escapado ni prepared statements. Cualquier endpoint es inyectable.

**Solución:**
Reemplazar la construcción de queries por string con `mysqli_real_escape_string()` en todos los valores antes de insertarlos. Para un fix más robusto a futuro, migrar a PDO con prepared statements. El fix inmediato y compatible con la arquitectura actual es escapar en `sql_post`, `sql_get`, `sql_put` y `sql_delete`.

**Código a modificar:**
- `sql_post()` línea 484: `$values .= "'$value',"` → escapar `$value`
- `sql_get()` línea 533: `$wheres .= "$key='$value' AND "` → escapar `$value`
- `sql_put()` línea 735: `$sets .= "$key='$value',"` y línea 749: `$wheres .= "$key='$value' AND "` → escapar `$value`
- `sql_delete()` línea 818: `$wheres .= "$key='$value' AND "` → escapar `$value`

**Notas:**
- Para MySQL usar `mysqli_real_escape_string($this->con, $value)`
- Para Oracle usar `addslashes()` como medida básica hasta tener bind variables
- Para MSSQL usar la función equivalente del driver

---

### ✅ Issue #2 — Inyección de nombre de tabla y columna
**Severidad:** 🔴 Crítico
**Estado:** ✅ Resuelto — 2026-02-21
**Archivos:** `ghost.php`
**Líneas afectadas:** 489, 508-511, 1272, 1284

**Descripción:**
El nombre de la tabla (`$option`) y los nombres de columnas (`$field`/`$key`) vienen directamente del request HTTP y se insertan en la query sin ninguna validación ni whitelist.

**Solución:**
Crear un método privado `sanitizeIdentifier($name)` que valide que el identificador solo contenga caracteres alfanuméricos y guiones bajos (`/^[a-zA-Z_][a-zA-Z0-9_]*$/`). Aplicarlo a todo nombre de tabla y columna antes de usarlo en una query.

**Código a agregar:**
```php
private function sanitizeIdentifier($name) {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
        $this->response('Invalid identifier', 400);
        exit;
    }
    return $name;
}
```

Llamarlo en `sql_post`, `sql_get`, `sql_put`, `sql_delete` sobre `$table`, `$option` y cada `$field`/`$key`.

---

### ✅ Issue #3 — Credenciales expuestas en error de conexión
**Severidad:** 🟠 Alto
**Estado:** ⬜ Pendiente
**Archivos:** `ghost.php`
**Líneas afectadas:** 335-336

**Descripción:**
Si la conexión MSSQL falla, se imprime `$host`, `$user` y `$pass` en la respuesta HTTP.

**Solución:**
Eliminar los datos sensibles del mensaje de error. Solo loguear internamente (con `error_log()`).

```php
// Antes:
echo "Conexión no se pudo establecer. $host $user $pass<br />";

// Después:
error_log("MSSQL connection failed for host: $host, user: $user");
echo "Error de conexión con la base de datos.";
```

---

### ✅ Issue #4 — Upload sin validación de tipo de archivo
**Severidad:** 🟠 Alto
**Estado:** ⬜ Pendiente
**Archivos:** `ghost.php`
**Líneas afectadas:** 848-882 (`save_file`)

**Descripción:**
`save_file()` acepta cualquier extensión de archivo, incluyendo `.php`. Un atacante puede subir un webshell que luego ejecutaría como código en el servidor.

**Solución:**
Agregar un parámetro `$allowedExtensions` a `save_file()` con un whitelist. Si no se especifica, usar una lista segura por defecto. Adicionalmente, validar el MIME type real con `finfo_file()`.

**Ejemplo de fix:**
```php
public function save_file($param, $path = NULL, $allowedExtensions = ['jpg','jpeg','png','gif','pdf','xlsx','csv','txt']) {
    // ...
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return FALSE; // extensión no permitida
    }
    // Validar MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $param['tmp_name']);
    finfo_close($finfo);
    // ... continuar solo si el MIME es aceptable
}
```

---

### ✅ Issue #5 — CORS completamente abierto
**Severidad:** 🟠 Alto
**Estado:** ⬜ Pendiente
**Archivos:** `ghost.php`
**Líneas afectadas:** 1242

**Descripción:**
`Access-Control-Allow-Origin: *` permite que cualquier sitio web haga requests a la API.

**Solución:**
Agregar una propiedad `$allowedOrigins` a la clase y validar el `Origin` del request contra esa lista. Si el origen no está permitido, no enviar el header (o enviar 403).

```php
public $allowedOrigins = []; // el dev configura: $ghost->allowedOrigins = ['https://miapp.com'];

// En run():
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (empty($this->allowedOrigins) || in_array($origin, $this->allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}
```

Si `$allowedOrigins` está vacío, no se envía el header (solo acceso desde el mismo dominio).

---

### ✅ Issue #6 — Comparación laxa `== NULL` en validación requerida
**Severidad:** 🟡 Medio
**Estado:** ⬜ Pendiente
**Archivos:** `ghost.php`
**Líneas afectadas:** 1066

**Descripción:**
`if ($wparam == NULL)` trata `0`, `"0"`, `""` y `false` como "campo ausente", rechazando valores legítimos como el número `0`.

**Solución:**
Cambiar a comparación estricta:
```php
// Antes:
if ($wparam == NULL) {

// Después:
if ($wparam === NULL) {
```

---

### ✅ Issue #7 — Bug lógico en `validator()` — falla con menos de 3 reglas
**Severidad:** 🟡 Medio
**Estado:** ⬜ Pendiente
**Archivos:** `ghost.php`
**Líneas afectadas:** 1206-1210

**Descripción:**
El validador solo retorna `TRUE` si exactamente 3 reglas pasan. Un campo con 1 o 2 reglas siempre retorna `FALSE` aunque todas sean válidas.

**Solución:**
Cambiar la condición para que valide que todas las reglas pasaron, no que sean exactamente 3:
```php
// Antes:
if (count($valid) == 3) {

// Después:
if (count($valid) == count($rules)) {
```

---

### ✅ Issue #8 — `get_connect()` usa variables no definidas
**Severidad:** 🔵 Bajo
**Estado:** ⬜ Pendiente
**Archivos:** `ghost.php`
**Líneas afectadas:** 410-415

**Descripción:**
`get_connect()` referencia variables locales `$host`, `$user`, etc. que no existen en ese scope, generando PHP Warnings que pueden filtrar info del stack.

**Solución:**
Usar las propiedades de la instancia:
```php
public function get_connect() {
    return $this->m_connect($this->host, $this->user, $this->pass, $this->db_name);
}
```

---

### ✅ Issue #9 — Fallback `random_string()` no criptográfico
**Severidad:** 🔵 Bajo
**Estado:** ⬜ Pendiente
**Archivos:** `ghost.php`
**Líneas afectadas:** 837-846, 452-458

**Descripción:**
`random_string()` usa `array_rand()`, que no es criptográficamente seguro. Se usa como fallback en `createToken()` cuando `random_bytes()` no está disponible.

**Solución:**
Eliminar el fallback inseguro y requerir PHP ≥ 7.0 (donde `random_bytes()` siempre está disponible). Si se necesita soporte para PHP 5, usar `openssl_random_pseudo_bytes()` como fallback.

```php
public function createToken($length = 32) {
    if (function_exists('random_bytes')) {
        return substr(bin2hex(random_bytes($length)), 0, $length);
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return substr(bin2hex(openssl_random_pseudo_bytes($length)), 0, $length);
    }
    throw new \RuntimeException('No hay fuente segura de aleatoriedad disponible.');
}
```

---

## Cómo actualizar este archivo

Cuando se resuelva un issue, cambiar su estado en la tabla de arriba y en su sección:
- ⬜ Pendiente
- 🔄 En progreso
- ✅ Resuelto
