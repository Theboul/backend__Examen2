# 📋 DOCUMENTACIÓN DE LÓGICA DE NEGOCIO
## Sistema de Gestión Académica - Backend Laravel

---

## 🔐 **1. MÓDULO DE AUTENTICACIÓN** (`AuthController`)

### **CU01: Iniciar Sesión** (`login`)
**Propósito:** Autenticar usuarios y generar token de acceso con Sanctum.

**Validaciones:**
- `username` (requerido): Puede ser email o código de docente
- `password` (requerido): Contraseña del usuario

**Lógica de Negocio:**
1. **Búsqueda Flexible:** Permite login con email (todos los usuarios) o código de docente (solo docentes)
2. **Verificación de Estado:** Valida que la cuenta esté activa (`activo = true`)
3. **Autenticación:** Compara password con hash usando `Hash::check()`
4. **Actualización de Acceso:** Registra `ultimo_acceso` al iniciar sesión
5. **Detección de Primer Ingreso:** Si `primer_ingreso` es null, lo marca como primer ingreso
6. **Generación de Token:** Crea token Sanctum con expiración de 2 horas (120 minutos)

**Respuesta Exitosa:**
- Datos del usuario (id, email, rol, perfil, docente si aplica)
- Token Bearer para autenticación posterior
- Flag `primer_ingreso` y `debe_cambiar_password`

---

### **CU02: Cerrar Sesión** (`logout`)
**Propósito:** Revocar token actual del usuario autenticado.

**Lógica:**
- Elimina el token actual usando `currentAccessToken()->delete()`
- Requiere autenticación previa con middleware `auth:sanctum`

---

### **CU03: Cambiar Contraseña en Primer Ingreso** (`cambiarPasswordPrimerIngreso`)
**Propósito:** Permitir al usuario cambiar su contraseña temporal.

**Validaciones:**
- `id_usuario` (requerido): ID del usuario
- `password_actual` (requerido): Contraseña actual para verificación
- `password_nueva` (requerido, mínimo 6 caracteres, confirmada)

**Lógica:**
1. Verifica que la contraseña actual sea correcta
2. Actualiza la contraseña usando `Hash::make()`
3. Usado típicamente después de primer ingreso

---

### **CU04: Activar/Desactivar Cuenta** (`toggleActivoCuenta`)
**Propósito:** Alternar el estado activo de una cuenta de usuario (solo Admin).

**Lógica:**
- Cambia el valor booleano de `activo` (`!$user->activo`)
- Retorna el nuevo estado de la cuenta

---

## 👥 **2. MÓDULO DE DOCENTES** (`DocenteController`)

### **CU05: Listar Docentes** (`index`)
**Propósito:** Obtener listado de docentes con filtros opcionales.

**Filtros Disponibles:**
- `activo` (boolean): Filtrar por estado activo/inactivo
- `id_tipo_contrato` (integer): Filtrar por tipo de contrato
- `buscar` (string): Buscar por nombres, apellidos o CI
- `incluir_inactivos` (boolean, default false): Incluir docentes inactivos

**Relaciones Cargadas:**
- `perfil`: Datos personales del docente
- `tipoContrato`: Tipo de contrato (Tiempo Completo, Hora Cátedra, etc.)
- `usuario.rol`: Rol del usuario (siempre "Docente")

**Transformación:**
- Agrega campo calculado `nombre_completo` desde el perfil

---

### **CU06: Crear Docente** (`store`)
**Propósito:** Registrar un nuevo docente en el sistema.

**Validaciones:**

**Datos de Usuario:**
- `usuario` (requerido, único, max 100): Nombre de usuario para login
- `email` (requerido, único, formato email): Correo electrónico
- `password` (requerido, min 6): Contraseña inicial

**Datos de Perfil:**
- `nombres` (requerido, max 100): Nombres del docente
- `apellidos` (requerido, max 100): Apellidos del docente
- `ci` (requerido, único, max 20): Cédula de identidad
- `telefono` (opcional, max 20): Número de teléfono
- `fecha_nacimiento` (opcional, fecha): Fecha de nacimiento
- `genero` (requerido, valores: M/F): Género

**Datos de Docente:**
- `id_tipo_contrato` (requerido, existe en tipo_contrato): Tipo de contrato
- `titulo` (requerido, max 150): Título profesional
- `especialidad` (opcional, max 100): Área de especialización
- `grado_academico` (opcional, max 100): Máximo grado académico
- `fecha_ingreso` (opcional, fecha, default: hoy): Fecha de ingreso

**Lógica de Negocio:**
1. **Asignación Automática de Rol:** Busca y asigna automáticamente el rol "Docente"
2. **Creación en 3 Pasos (Transacción):**
   - Crea registro en `users` con password hasheado
   - Crea registro en `perfil_usuario` vinculado al usuario
   - Crea registro en `docente` con código autogenerado
3. **Código Autogenerado:** Llama a `Docente::generarCodigoDocente()` (formato único)
4. **Estado Inicial:** Crea el docente como activo por defecto
5. **Bitácora:** Registra la acción de creación

**Caso de Error:**
- Si no existe el rol "Docente" activo, retorna error 422

---

### **CU07: Ver Detalle de Docente** (`show`)
**Propósito:** Obtener información completa de un docente específico.

**Relaciones Cargadas:**
- Perfil completo, tipo de contrato, usuario y rol

---

### **CU08: Actualizar Docente** (`update`)
**Propósito:** Modificar datos de un docente existente.

**Validaciones:**
- Todas las validaciones de creación pero con `Rule::unique()->ignore()` para el registro actual
- `password` es opcional (solo se actualiza si se proporciona)

**Lógica de Negocio:**
1. **Actualización Parcial en 3 Tablas:**
   - `users`: email, password (si se proporciona)
   - `perfil_usuario`: nombres, apellidos, ci, teléfono, etc.
   - `docente`: tipo_contrato, título, especialidad, etc.
2. **Transacción:** Rollback automático si falla cualquier actualización
3. **Bitácora:** Registra la actualización con nombre completo y código

---

### **CU09: Desactivar Docente** (`destroy`)
**Propósito:** Realizar eliminación lógica de un docente (soft delete).

**Validaciones:**
- Llama a `$docente->puedeDesactivarse()` para verificar si tiene asignaciones activas

**Lógica:**
- Si tiene asignaciones activas, retorna error 422
- Cambia `activo = false` en lugar de eliminar físicamente
- Registra en bitácora

---

### **CU10: Reactivar Docente** (`reactivar`)
**Propósito:** Volver a activar un docente previamente desactivado.

**Lógica:**
- Cambia `activo = true`
- Permite que el docente vuelva a recibir asignaciones

---

## 🏛️ **3. MÓDULO DE CARRERAS** (`CarreraController`)

### **CU11: Listar Carreras** (`index`)
**Propósito:** Obtener todas las carreras registradas.

**Filtros:**
- `incluir_inactivas` (boolean, default false): Si es false, solo muestra activas

**Orden:** Por nombre alfabéticamente

---

### **CU12: Crear Carrera** (`store`)
**Propósito:** Registrar una nueva carrera académica.

**Validaciones:**
- `nombre` (requerido, único, max 150): Nombre de la carrera
- `codigo` (requerido, único, max 20): Código identificador
- `duracion_anios` (requerido, min 1, max 10): Duración en años

**Lógica:**
1. Valida unicidad de nombre y código
2. Crea carrera con `activo = true` por defecto
3. Los campos `fecha_creacion` y `fecha_modificacion` se manejan automáticamente con timestamps
4. Registra en bitácora con formato: "Carrera creada: {nombre} ({código}) - ID: {id}"

---

### **CU13: Ver Carrera** (`show`)
**Propósito:** Obtener detalles de una carrera específica.

---

### **CU14: Actualizar Carrera** (`update`)
**Propósito:** Modificar datos de una carrera existente.

**Validaciones:**
- Mismas que crear, pero con `Rule::unique()->ignore()` para permitir mantener valores actuales

---

## 📚 **4. MÓDULO DE MATERIAS** (`MateriaController`)

### **CU15: Listar Materias** (`index`)
**Propósito:** Obtener materias con relaciones de carrera y semestre.

**Filtros:**
- `incluir_inactivas` (boolean, default false): Incluir materias inactivas

**Relaciones Cargadas:**
- `carrera`: Solo id, nombre y código
- `semestre`: Solo id y nombre

**Orden:** Por carrera → semestre → nombre

---

### **CU16: Crear Materia** (`store`)
**Propósito:** Registrar una nueva materia académica.

**Validaciones:**
- `id_semestre` (requerido, existe en semestre)
- `id_carrera` (requerido, existe en carrera)
- `nombre` (requerido, max 150): Nombre de la materia
- `sigla` (opcional, única, max 10): Código corto (ej: "MAT101")
- `creditos` (opcional, min 0, max 20): Créditos académicos
- `carga_horaria_semestral` (opcional, min 0, max 400): Horas totales

**Lógica:**
1. Crea materia con `activo = true`
2. Registra en bitácora con formato: "Materia creada: {sigla} - {nombre} (ID: {id})"
3. Usa transacción para asegurar consistencia

---

### **CU17: Actualizar Materia** (`update`)
**Propósito:** Modificar datos de una materia existente.

**Validaciones:**
- Permite cambiar `activo` explícitamente
- Sigla debe ser única excepto para el registro actual

---

### **CU18: Desactivar Materia** (`destroy`)
**Propósito:** Realizar soft delete de una materia.

**Validaciones:**
- Llama a `$materia->puedeDesactivarse()` para verificar si tiene grupos o asignaciones activas

**Lógica:**
- Si tiene dependencias activas, retorna error 400
- Cambia `activo = false`

---

## 👥 **5. MÓDULO DE GRUPOS** (`GrupoController`)

### **CU19: Listar Grupos** (`index`)
**Propósito:** Obtener grupos con sus materias asociadas (relación many-to-many).

**Filtros:**
- `incluir_inactivos` (boolean, default false)
- `nombre` (string): Búsqueda por nombre con ILIKE (insensible a mayúsculas)

**Relaciones:**
- `materiaGrupos.materia`: Carga las materias asociadas a través de la tabla intermedia

**Orden:** Por `id_grupo`

---

### **CU20: Crear Grupo** (`store`)
**Propósito:** Crear un nuevo grupo sin asociarlo a materias (eso se hace en materia-grupos).

**Validaciones:**
- `nombre` (requerido, max 50): Nombre del grupo (ej: "A", "B", "1A")
- `descripcion` (opcional): Descripción adicional
- `capacidad_maxima` (requerido, min 1): Cupos totales del grupo
- `cupos` (opcional, min 0, default 0): Cupos ocupados inicialmente

**Lógica de Negocio:**
1. **Validación de Cupos:** Verifica que `cupos <= capacidad_maxima`
2. **Auditoría:** Registra `creado_por` con el ID del perfil del usuario autenticado
3. **Estado Inicial:** Crea el grupo como `activo = true`
4. **No Requiere Materia:** El grupo se crea independiente y luego se asocia a materias

---

### **CU21: Actualizar Grupo** (`update`)
**Propósito:** Modificar datos de un grupo existente.

**Validaciones:**
- Permite actualizar nombre, descripción, capacidad_maxima y cupos
- Verifica que los cupos no excedan la capacidad máxima (usando valores nuevos o actuales)

---

### **CU22: Desactivar Grupo** (`destroy`)
**Propósito:** Desactivar un grupo lógicamente.

**Validaciones:**
- Llama a `$grupo->puedeDesactivarse()` para verificar si tiene asignaciones activas en materia-grupos

**Lógica:**
- Si tiene asignaciones activas, retorna error 422: "No se puede desactivar: tiene asignaciones activas"
- Cambia `activo = false`

---

### **CU23: Reactivar Grupo** (`reactivar`)
**Propósito:** Volver a activar un grupo desactivado.

---

### **CU24: Obtener Grupos para Select** (`getGruposForSelect`)
**Propósito:** Endpoint optimizado para llenar dropdowns en el frontend.

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {"value": 1, "label": "A"},
    {"value": 2, "label": "B"}
  ]
}
```

---

## 🏢 **6. MÓDULO DE AULAS** (`AulaController`)

### **CU25: Listar Aulas** (`index`)
**Propósito:** Obtener aulas con filtros de disponibilidad.

**Filtros:**
- `disponibles` (boolean): Solo aulas activas y sin mantenimiento
- `en_mantenimiento` (boolean): Solo aulas en mantenimiento
- `incluir_inactivas` (boolean): Incluir aulas inactivas

**Relaciones:**
- `tipoAula`: Tipo de aula (Teórica, Laboratorio, Auditorio, etc.)

**Orden:** Por piso → nombre

---

### **CU26: Crear Aula** (`store`)
**Propósito:** Registrar una nueva aula física.

**Validaciones:**
- `nombre` (requerido, único, max 100): Nombre del aula (ej: "LAB-301")
- `capacidad` (requerido, min 1): Cantidad de estudiantes que soporta
- `piso` (opcional, default 0): Número de piso
- `id_tipo_aula` (requerido, existe en tipo_aula)
- `mantenimiento` (opcional, boolean, default false): Estado de mantenimiento

**Lógica:**
1. Valida unicidad del nombre del aula
2. Crea aula como `activo = true` por defecto
3. Registra en bitácora con formato: "Aula creada: {nombre} (Capacidad: {capacidad}) - ID: {id}"

---

### **CU27: Actualizar Aula** (`update`)
**Propósito:** Modificar datos de un aula existente.

**Validaciones:**
- Permite cambiar nombre (único excepto para el registro actual)
- Permite cambiar capacidad, piso, tipo y estado de mantenimiento

---

## 🔗 **7. MÓDULO DE MATERIA-GRUPOS** (`MateriaGrupoController`)

### **CU28: Listar Materia-Grupos** (`index`)
**Propósito:** Obtener todas las relaciones materia-grupo de la gestión activa.

**Validaciones Iniciales:**
- Verifica que exista una gestión activa, sino retorna error 422

**Relaciones Cargadas:**
- `materia`: Sigla y nombre
- `grupo`: Nombre del grupo
- `gestion`: Año y semestre
- `asignacionDocenteActiva.docente.usuario.perfil`: Docente asignado si existe

**Transformación de Datos:**
```json
{
  "id_materia_grupo": 1,
  "materia": {"sigla": "MAT101", "nombre": "Matemáticas I"},
  "grupo": {"nombre": "A"},
  "gestion": "1/2025",
  "docente_asignado": "Juan Pérez López",
  "observacion": "Grupo prioritario",
  "activo": true,
  "fecha_creacion": "2025-01-15 10:30:00"
}
```

---

### **CU29: Crear Materia-Grupo** (`store`)
**Propósito:** Asociar una materia con un grupo en la gestión activa.

**Validaciones:**
- `id_materia` (requerido, existe en materia)
- `id_grupo` (requerido, existe en grupo)
- `observacion` (opcional, max 500)

**Lógica de Negocio:**
1. **Gestión Activa Requerida:** Verifica que exista gestión activa, sino error 422
2. **Validación de Duplicados:** No permite crear la misma combinación materia-grupo-gestión si ya existe activa
3. **Creación Automática:** Asocia automáticamente a la gestión activa
4. **Estado Inicial:** Crea como `activo = true`
5. **Logging:** Registra logs detallados para debugging

**Caso de Uso:**
Cuando el coordinador quiere habilitar el grupo "A" para la materia "MAT101" en el semestre actual.

---

### **CU30: Ver Materia-Grupo** (`show`)
**Propósito:** Obtener detalles de una relación materia-grupo específica.

**Relaciones:**
- Materia, grupo, gestión y asignación de docente activa

---

### **CU31: Actualizar Materia-Grupo** (`update`)
**Propósito:** Modificar observaciones o datos de una relación materia-grupo.

---

## 📅 **8. MÓDULO DE GESTIONES** (`GestionController`)

### **CU32: Listar Gestiones** (`index`)
**Propósito:** Obtener todas las gestiones académicas (periodos).

**Orden:** Por año descendente → semestre descendente (más recientes primero)

---

### **CU33: Crear Gestión** (`store`)
**Propósito:** Crear un nuevo periodo académico.

**Validaciones:**
- `anio` (requerido, min 2020, max 2030): Año académico
- `semestre` (requerido, valores: 1 o 2): Semestre del año
- `fecha_inicio` (requerido, fecha): Fecha de inicio del semestre
- `fecha_fin` (requerido, fecha, after:fecha_inicio): Fecha de fin (debe ser posterior al inicio)

**Lógica de Negocio:**
1. **Validación de Duplicados:** No permite crear dos gestiones con mismo año y semestre
2. **Estado Inicial:** Crea la gestión como `activo = false` (debe activarse manualmente)
3. **Bitácora:** Registra creación con formato: "Gestión creada: {año}-{semestre}"

**Caso de Uso:**
El administrador crea la gestión "2025-1" con fechas de enero a junio.

---

### **CU34: Activar Gestión** (`activar`)
**Propósito:** Activar una gestión como el periodo académico actual.

**Lógica:**
1. Llama a `$gestion->activar()` que internamente:
   - Desactiva TODAS las demás gestiones
   - Activa solo la gestión solicitada
2. **Patrón Singleton:** Solo puede haber UNA gestión activa a la vez
3. Registra en bitácora

**Impacto:**
- Todas las operaciones de materia-grupos, asignaciones y horarios se crean para la gestión activa

---

### **CU35: Reactivar Gestión** (`reactivar`)
**Propósito:** Reactivar una gestión previamente desactivada sin desactivar otras.

**Diferencia con `activar`:**
- `activar`: Hace que sea la ÚNICA gestión activa
- `reactivar`: Solo cambia su estado a activo sin afectar otras

---

## 👨‍🏫 **9. MÓDULO DE ASIGNACIÓN DE DOCENTES** (`AsignacionDocenteController`)

### **CU36: Listar Asignaciones** (`index`)
**Propósito:** Obtener asignaciones de docentes a materia-grupos.

**Filtros:**
- `id_gestion` (integer): Filtrar por gestión específica
- `id_docente` (integer): Filtrar por docente específico

**Relaciones Cargadas:**
- `docente.perfil`: Datos del docente
- `materiaGrupo.materia`: Materia asignada
- `materiaGrupo.grupo`: Grupo asignado
- `materiaGrupo.gestion`: Gestión del periodo
- `estado`: Estado de la asignación

**Orden:** Por fecha de asignación descendente (más recientes primero)

---

### **CU37: Asignar Docente** (`store`) - **CASO DE USO CRÍTICO**
**Propósito:** Asignar un docente a una materia-grupo con validaciones de carga horaria.

**Validaciones del Request (`AsignarDocenteRequest`):**
- `id_docente` (requerido, existe en docente)
- `id_materia_grupo` (requerido, existe en materia_grupo)
- `hrs_asignadas` (requerido, min 1, max 40): Horas semanales asignadas

**Lógica de Negocio (Validaciones en Orden):**

1. **Gestión Activa Requerida:**
   - Verifica que exista gestión activa
   - Error 422: "No hay gestión académica activa"

2. **Validación de Materia-Grupo:**
   - Verifica que el materia-grupo exista y esté activo
   - Error 422: "La relación materia-grupo no existe"

3. **Validación de Docente:**
   - Verifica que el docente exista
   - Verifica que esté activo
   - Error 422: "El docente está inactivo"

4. **Validación de Duplicados:**
   - Llama a `AsignacionDocente::existeAsignacion()`
   - Verifica que no exista la misma asignación docente-materiaGrupo activa
   - Error 422: "Ya existe esta asignación"

5. **Validación de Grupo:**
   - Llama a `AsignacionDocente::materiaGrupoTieneDocente()`
   - Verifica que el materia-grupo no tenga otro docente asignado
   - Error 422: "Este grupo ya tiene un docente asignado"
   - **Regla de Negocio:** Un grupo solo puede tener UN docente por materia

6. **Validación de Carga Horaria:**
   - Llama a `AsignacionDocente::excedeCargarMaxima()`
   - Suma las horas actuales del docente en la gestión
   - Compara con las horas máximas del tipo de contrato
   - Error 422: "El docente excedería su carga máxima ({hrs_maximas} hrs)"

**Proceso de Creación:**
1. Crea asignación con `id_estado = 1` (estado "Asignado")
2. Marca como `activo = true`
3. Registra en bitácora con formato: "Docente {nombre} asignado a {materia} - Grupo {grupo} ({hrs} hrs)"

**Caso de Uso Real:**
El coordinador asigna al "Ing. Juan Pérez" (contrato 40hrs) a "MAT101 - Grupo A" con 4 horas semanales. El sistema valida que Juan no exceda sus 40hrs y que el Grupo A no tenga otro docente para MAT101.

---

### **CU38: Ver Asignación** (`show`)
**Propósito:** Obtener detalles completos de una asignación.

**Relaciones:**
- Docente con perfil y tipo de contrato
- Materia-grupo con materia, grupo y gestión
- Estado de la asignación

---

### **CU39: Actualizar Horas Asignadas** (`update`)
**Propósito:** Modificar las horas semanales de una asignación existente.

**Validaciones:**
- `hrs_asignadas` (requerido, min 1, max 40)

**Lógica:**
1. Calcula horas actuales del docente SIN considerar esta asignación
2. Suma las nuevas horas
3. Verifica que no exceda las horas máximas del tipo de contrato
4. Si excede, retorna error 422
5. Actualiza las horas y registra en bitácora

**Ejemplo:**
Docente tiene 36hrs asignadas (incluyendo 4hrs de esta materia). Quiere cambiar a 6hrs. Sistema calcula: 36-4+6=38, está dentro del límite de 40hrs, permite el cambio.

---

## 📊 **10. MÓDULO DE BITÁCORA** (`BitacoraController`)

### **CU40: Listar Bitácora** (`index`)
**Propósito:** Obtener historial de acciones del sistema con filtros.

**Filtros:**
- `usuario` (string): Busca por nombre de usuario o nombre/apellido en perfil (ILIKE)
- `accion` (string): Filtra por tipo de acción (CREAR, ACTUALIZAR, ELIMINAR, etc.)
- `fecha` (date): Filtra por fecha específica

**Paginación:**
- Soporta paginación con `page_size` (default 10 registros)

**Relaciones:**
- `usuario.perfil`: Datos del usuario que realizó la acción

**Transformación:**
- Si el usuario tiene perfil, muestra `nombre_completo`
- Si no tiene perfil, muestra `usuario` (nombre de login)
- Si es null, muestra "Anónimo"

**Respuesta JSON:**
```json
{
  "success": true,
  "bitacoras": [...],
  "current_page": 1,
  "last_page": 5,
  "total": 50,
  "next_page_url": "...",
  "prev_page_url": "..."
}
```

---

### **CU41: Registrar en Bitácora** (`registrar`)
**Propósito:** Método estático para registrar acciones desde cualquier controlador.

**Parámetros:**
- `$accion` (string): Tipo de acción (CREAR, ACTUALIZAR, ELIMINAR, LOGIN, etc.)
- `$descripcion` (string): Descripción detallada de la acción

**Lógica:**
1. Obtiene el usuario autenticado actual
2. Obtiene la IP de la petición
3. Si no hay usuario autenticado, no registra (evita errores en rutas públicas)
4. Registra automáticamente la fecha actual

**Uso en Otros Controladores:**
```php
Bitacora::registrar('CREAR', "Docente creado: Juan Pérez - Código: 12345");
```

---

### **CU42: Generar Reportes** (`getReport`)
**Propósito:** Exportar bitácora en diferentes formatos.

**Formatos Soportados:**
- `pdf`: Genera PDF con Laravel-DomPDF
- `excel`: Genera Excel con Maatwebsite/Excel
- `word`: Genera Word con PhpWord

**Lógica:**
1. Aplica los mismos filtros que en `index()`
2. Transforma los datos para incluir `nombre_usuario_plano`
3. Genera el archivo según el formato solicitado
4. Retorna descarga del archivo

**Uso:**
```
GET /api/bitacoras/report?formato=pdf&fecha=2025-11-13
```

---

## 📋 **RESUMEN DE VALIDACIONES COMUNES**

### **Validaciones de Unicidad con `Rule::unique()`**
- **Crear:** Valida que el campo sea único en toda la tabla
- **Actualizar:** Usa `ignore()` para excluir el registro actual

```php
// Crear
'nombre' => 'required|unique:carrera,nombre'

// Actualizar
'nombre' => ['required', Rule::unique('carrera', 'nombre')->ignore($id, 'id_carrera')]
```

---

### **Validaciones de Existencia con `exists`**
Verifican que la clave foránea exista en la tabla referenciada:

```php
'id_carrera' => 'required|exists:carrera,id_carrera'
'id_tipo_contrato' => 'required|exists:tipo_contrato,id_tipo_contrato'
```

---

### **Validaciones de Rango**
```php
'capacidad' => 'required|integer|min:1'
'duracion_anios' => 'required|integer|min:1|max:10'
'hrs_asignadas' => 'required|integer|min:1|max:40'
```

---

### **Validaciones de Comparación**
```php
'fecha_fin' => 'required|date|after:fecha_inicio'
'cupos' <= 'capacidad_maxima' // Validación manual en lógica
```

---

## 🔐 **PATRONES DE SEGURIDAD**

### **1. Soft Deletes (Eliminación Lógica)**
Todos los módulos usan `activo` (boolean) en lugar de eliminar físicamente:
- Permite auditoría completa
- Permite reactivación
- Preserva integridad referencial

### **2. Transacciones Database**
Operaciones críticas usan `DB::beginTransaction()`:
- Creación de docentes (3 tablas: users, perfil_usuario, docente)
- Asignaciones con validaciones múltiples
- Activación de gestiones (desactiva otras)

### **3. Validaciones en Modelo**
Métodos de negocio en modelos:
- `puedeDesactivarse()`: Verifica dependencias antes de desactivar
- `generarCodigoDocente()`: Genera códigos únicos
- `getActiva()`: Obtiene gestión activa (patrón Singleton)

### **4. Auditoría Completa**
Todos los cambios críticos se registran en bitácora:
- Usuario que realizó la acción
- IP del cliente
- Fecha y hora exacta
- Descripción detallada

---

## 📐 **ARQUITECTURA DE RELACIONES**

### **Relaciones Many-to-Many con Tabla Intermedia**
```
Materia ↔ MateriaGrupo ↔ Grupo
```
- Un grupo puede tener múltiples materias (en diferentes periodos)
- Una materia puede tener múltiples grupos
- La tabla intermedia `materia_grupo` incluye `id_gestion` para el periodo

### **Relaciones One-to-Many**
```
TipoContrato → Docente (1:N)
Carrera → Materia (1:N)
Semestre → Materia (1:N)
MateriaGrupo → AsignacionDocente (1:1 activo, 1:N histórico)
```

### **Relaciones One-to-One**
```
User ↔ PerfilUsuario (1:1)
User ↔ Docente (1:1, opcional)
```

---

## 🎯 **CASOS DE USO PRINCIPALES**

### **Flujo Completo de Asignación Docente:**

1. **Admin crea gestión 2025-1** (GestionController.store)
2. **Admin activa gestión 2025-1** (GestionController.activar)
3. **Coordinador crea materia-grupo:** MAT101 + Grupo A (MateriaGrupoController.store)
4. **Coordinador asigna docente:** Juan Pérez a MAT101-A con 4hrs (AsignacionDocenteController.store)
   - Sistema valida: gestión activa ✓
   - Sistema valida: materia-grupo existe ✓
   - Sistema valida: docente activo ✓
   - Sistema valida: sin duplicados ✓
   - Sistema valida: grupo sin otro docente ✓
   - Sistema valida: carga horaria (36hrs + 4hrs = 40hrs OK) ✓
5. **Sistema registra en bitácora:** "Docente Juan Pérez asignado a MAT101 - Grupo A (4 hrs)"

---

## 📝 **CONVENCIONES DEL CÓDIGO**

### **Nombres de Métodos:**
- `index()`: Listar con filtros
- `store()`: Crear nuevo registro
- `show($id)`: Ver detalle
- `update($id)`: Actualizar existente
- `destroy($id)`: Soft delete (cambiar activo=false)
- `reactivar($id)`: Volver a activar

### **Respuestas JSON Estandarizadas:**
```json
{
  "success": true/false,
  "message": "Mensaje descriptivo",
  "data": { ... },
  "errors": { ... }
}
```

### **Códigos HTTP:**
- `200`: Operación exitosa
- `201`: Recurso creado exitosamente
- `400`: Error de lógica de negocio
- `401`: No autenticado
- `403`: No autorizado (sin permisos)
- `404`: Recurso no encontrado
- `422`: Error de validación
- `500`: Error interno del servidor

---

**Documento generado el:** 13 de noviembre de 2025  
**Versión Backend:** Laravel 12.38.0 con Sanctum  
**Base de Datos:** PostgreSQL (Neon Cloud)  
**Arquitectura:** RESTful API con autenticación Bearer Token
