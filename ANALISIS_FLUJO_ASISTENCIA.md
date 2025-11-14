# 📊 ANÁLISIS DE FLUJO DE ASISTENCIA
## Sistema de Gestión Académica

---

## 🔍 **ESTADO ACTUAL DEL SISTEMA**

### **Tablas Principales (En orden jerárquico):**

```
1. gestion (periodo académico)
   └── 2. materia_grupo (materia + grupo + gestión)
       └── 3. asignacion_docente (docente asignado a materia-grupo)
           └── 4. horario_clase (aula + día + bloque + tipo_clase)
               └── 5. asistencia (registro de asistencia del docente)
```

---

## ✅ **LO QUE YA ESTÁ IMPLEMENTADO**

### **1. Estructura de Tablas (COMPLETA)**

#### **✅ Tabla `asistencia`**
```sql
CREATE TABLE asistencia (
    id_asistencia SERIAL PRIMARY KEY,
    id_asignacion_docente INTEGER REFERENCES asignacion_docente(id_asignacion_docente),
    id_horario_clase INTEGER REFERENCES horario_clase(id_horario_clase),
    id_estado INTEGER REFERENCES estado(id_estado),
    fecha_registro DATE NOT NULL,
    hora_registro TIME NOT NULL,
    tipo_registro VARCHAR(20), -- 'BOTON_GPS', 'QR_VALIDADO', 'MANUAL_ADMIN'
    observacion TEXT,
    CONSTRAINT uq_asistencia_clase_dia UNIQUE(id_horario_clase, fecha_registro)
);
```

**Lógica de Negocio:**
- Un docente NO puede registrar asistencia 2 veces para la misma clase el mismo día
- La restricción `UNIQUE(id_horario_clase, fecha_registro)` garantiza esto

---

#### **✅ Tabla `horario_clase`**
```sql
CREATE TABLE horario_clase (
    id_horario_clase SERIAL PRIMARY KEY,
    id_asignacion_docente INTEGER REFERENCES asignacion_docente(id_asignacion_docente),
    id_aula INTEGER REFERENCES aula(id_aula),
    id_dia INTEGER REFERENCES dia(id_dia),
    id_bloque_horario INTEGER REFERENCES bloque_horario(id_bloque_horario),
    id_tipo_clase INTEGER REFERENCES tipo_clase(id_tipo_clase),
    id_estado INTEGER REFERENCES estado(id_estado),
    activo BOOLEAN DEFAULT true,
    fecha_creacion TIMESTAMP DEFAULT NOW()
);
```

**Lógica de Negocio:**
- Define CUÁNDO y DÓNDE se imparte una clase
- Vincula asignación_docente (quién y qué materia) con aula, día, bloque

---

#### **✅ Tabla `asignacion_docente`**
```sql
CREATE TABLE asignacion_docente (
    id_asignacion_docente SERIAL PRIMARY KEY,
    id_docente INTEGER REFERENCES docente(id_docente),
    id_materia_grupo INTEGER REFERENCES materia_grupo(id_materia_grupo),
    id_estado INTEGER REFERENCES estado(id_estado),
    hrs_asignadas INTEGER NOT NULL,
    activo BOOLEAN DEFAULT true,
    fecha_asignacion TIMESTAMP DEFAULT NOW(),
    fecha_modificacion TIMESTAMP
);
```

**Lógica de Negocio:**
- Define QUÉ docente enseña QUÉ materia-grupo
- Controla carga horaria (máximo según tipo_contrato)

---

### **2. Modelos Eloquent (COMPLETOS)**

#### **✅ Modelo `Asistencia`**
```php
protected $fillable = [
    'id_asignacion_docente',
    'id_horario_clase',
    'id_estado',
    'fecha_registro',
    'hora_registro',
    'tipo_registro',
    'observacion',
];

// Relaciones
public function asignacionDocente() // → Docente + MateriaGrupo
public function horarioClase()      // → Aula + Día + Bloque
public function estado()            // → Presente, Tardanza, Ausente
```

#### **✅ Modelo `HorarioClase`**
```php
protected $fillable = [
    'id_asignacion_docente',
    'id_aula',
    'id_dia',
    'id_bloque_horario',
    'id_tipo_clase',
    'activo',
    'id_estado',
];

// Relaciones
public function asignacionDocente() // → Docente + MateriaGrupo + Gestión
public function aula()
public function dia()
public function bloqueHorario()
public function tipoClase()
public function estado()

// Scopes
public function scopePublicados($query)  // Estado: PUBLICADA
public function scopeAprobados($query)   // Estado: APROBADA
public function scopeBorradores($query)  // Estado: BORRADOR
```

---

### **3. Controladores Implementados**

#### **✅ AsistenciaController (CU9 - Registro de Asistencia)**

**Métodos Implementados:**

**a) `registrarAsistencia()` - POST /api/asistencia/registrar**
- **Propósito:** Registro por botón con GPS
- **Validaciones:**
  1. ✅ `id_horario_clase` existe
  2. ✅ Coordenadas GPS válidas (lat, long)
  3. ✅ Usuario autenticado es docente
  4. ✅ Horario existe y está activo
  5. ✅ El horario pertenece al docente (via asignacion_docente)
  6. ✅ Es el día correcto (lunes=1, sábado=6)
  7. ✅ Geovalla: dentro de 250m de la facultad
  8. ✅ Ventana de tiempo: 5 min antes hasta 20 min después
  9. ✅ No duplicado (unique constraint)

**Lógica de Estados:**
```php
// Si registra dentro de 10 min después del inicio
→ Estado: PRESENTE (Puntual)

// Si registra entre 10-20 min después del inicio
→ Estado: TARDANZA

// Si registra después de 20 min
→ Estado: AUSENTE (Demasiado tarde)
```

**b) `registrarAsistenciaQR()` - POST /api/asistencia/registrar-qr**
- **Propósito:** Registro escaneando QR del aula
- **Validación Adicional:**
  - ✅ `id_aula_escaneada` debe coincidir con `horario_clase.id_aula`
  - Si no coincide: error "Aula incorrecta"

---

#### **✅ HorarioClaseController**

**Métodos Relacionados:**

**a) `cargaHorariaPersonal()` - GET /api/docente/horarios-personales (CU10)**
- **Propósito:** Docente ve SUS horarios de la semana
- **Filtros:**
  - Gestión activa
  - Solo horarios publicados
  - Solo horarios del docente autenticado

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "id_horario_clase": 1,
      "materia": "MAT101 - Matemáticas I",
      "grupo": "A",
      "aula": "LAB-301",
      "dia": "Lunes",
      "bloque": "08:00 - 10:00",
      "tipo_clase": "Teórica",
      "puede_registrar_asistencia": true // Si es hoy y está en ventana de tiempo
    }
  ]
}
```

---

#### **✅ JustificacionController (CU20)**

**Métodos Implementados:**

**a) `store()` - POST /api/asistencia/{id}/justificar**
- **Propósito:** Docente justifica una ausencia
- **Validaciones:**
  1. ✅ La asistencia existe
  2. ✅ La asistencia pertenece al docente autenticado
  3. ✅ El estado es AUSENTE o TARDANZA
  4. ✅ No tiene justificación previa
  5. ✅ Motivo requerido (max 500 caracteres)
  6. ✅ Documento opcional (PDF/JPG/PNG, max 5MB)

**Flujo:**
```
1. Docente registra asistencia → Estado: AUSENTE
2. Docente envía justificación → Estado: EN_REVISION
3. Coordinador revisa → Estado: JUSTIFICADO o RECHAZADO
```

---

#### **✅ RevisionJustificacionController (CU21)**

**a) `index()` - GET /api/justificaciones**
- Lista justificaciones pendientes de revisión

**b) `revisar()` - POST /api/justificaciones/{id}/revisar**
- Coordinador aprueba/rechaza justificación
- Si aprueba: cambia estado de asistencia a JUSTIFICADO

---

#### **✅ ReporteAsistenciaController (CU11)**

**a) `generarReporte()` - GET /api/reportes/asistencia**
- **Filtros:**
  - `id_gestion` (requerido)
  - `id_docente` (opcional)
  - `id_materia` (opcional)
  - `id_grupo` (opcional)
  - `fecha_inicio` y `fecha_fin` (opcional)
  - `exportar`: pdf / excel

**Estadísticas Calculadas:**
```php
[
    'total_registros' => 100,
    'presentes' => 85,
    'tardanzas' => 10,
    'ausentes' => 3,
    'justificados' => 2,
    'porcentaje_asistencia' => 95.0
]
```

---

## 📋 **FLUJO COMPLETO DE DATOS**

### **Flujo de Creación (Setup Inicial):**

```
1. Admin crea GESTIÓN 2025-1
   └─> Activa la gestión

2. Admin/Coordinador crea MATERIA-GRUPO
   └─> Materia: MAT101
   └─> Grupo: A
   └─> Gestión: 2025-1

3. Coordinador ASIGNA DOCENTE
   └─> Docente: Juan Pérez (ID: 1)
   └─> Materia-Grupo: MAT101-A
   └─> Horas: 4hrs semanales
   └─> Validaciones:
       ✓ Gestión activa existe
       ✓ Docente activo
       ✓ No excede carga horaria máxima
       ✓ Grupo no tiene otro docente

4. Coordinador crea HORARIO DE CLASE
   └─> Asignación: Juan Pérez - MAT101-A
   └─> Día: Lunes (id_dia: 1)
   └─> Bloque: 08:00-10:00 (id_bloque_horario: 1)
   └─> Aula: LAB-301 (id_aula: 15)
   └─> Tipo: Teórica (id_tipo_clase: 1)
   └─> Estado: PUBLICADA

5. Coordinador publica horarios (batch)
   └─> Cambia estado de BORRADOR → PUBLICADA
   └─> Solo horarios publicados son visibles para docentes
```

---

### **Flujo de Registro de Asistencia (Día a Día):**

```
LUNES 18/11/2025 - 08:05 AM

1. Docente abre app móvil
   └─> GET /api/docente/horarios-personales
   └─> Ve: "MAT101-A - LAB-301 - 08:00-10:00 - Puede registrar"

2. Docente presiona "Registrar Asistencia"
   └─> App obtiene GPS: lat=-17.7833, long=-63.1822
   └─> POST /api/asistencia/registrar
   └─> Body: {
         "id_horario_clase": 1,
         "coordenadas": {"latitud": -17.7833, "longitud": -63.1822}
       }

3. Backend valida (AsistenciaController.procesarRegistro):
   ✓ Horario existe y pertenece al docente
   ✓ Es lunes (día correcto)
   ✓ Hora actual: 08:05 (dentro de ventana: 07:55-08:20)
   ✓ GPS dentro de geovalla (250m)
   ✓ No tiene registro previo hoy para esta clase

4. Backend determina estado:
   └─> Hora inicio: 08:00
   └─> Hora registro: 08:05 (5 min después)
   └─> 5 min <= 10 min → Estado: PRESENTE ✓

5. Backend crea registro:
   INSERT INTO asistencia (
     id_asignacion_docente: 1,
     id_horario_clase: 1,
     id_estado: 3, -- PRESENTE
     fecha_registro: '2025-11-18',
     hora_registro: '08:05:00',
     tipo_registro: 'BOTON_GPS',
     observacion: null
   )

6. Backend registra bitácora:
   "Docente 1 registró asistencia por GPS para clase ID 1"

7. Response al frontend:
   {
     "success": true,
     "message": "✓ Asistencia registrada correctamente",
     "data": { ...asistencia }
   }
```

---

### **Flujo Alternativo - Registro con QR:**

```
1. Docente escanea QR pegado en puerta del aula LAB-301
   └─> QR contiene: {"id_aula": 15}

2. App envía:
   POST /api/asistencia/registrar-qr
   Body: {
     "id_horario_clase": 1,
     "coordenadas": {...},
     "id_aula_escaneada": 15
   }

3. Backend valida ADICIONAL:
   ✓ Horario tiene id_aula: 15
   ✓ id_aula_escaneada (15) == horario.id_aula (15) → OK

4. Si coincide: registro exitoso
   Si no coincide: error "Aula incorrecta - Escanee QR del aula LAB-301"
```

---

### **Flujo de Justificación:**

```
ESCENARIO: Docente llegó tarde (20 min después)

1. Sistema registró automáticamente:
   └─> Estado: AUSENTE (llegó después de ventana permitida)

2. Docente ve en su historial:
   └─> GET /api/asistencia (propia)
   └─> Ve: "AUSENTE - 18/11/2025 - MAT101-A"

3. Docente envía justificación:
   └─> POST /api/asistencia/{id}/justificar
   └─> Body: {
         "motivo": "Tráfico por accidente en 4to anillo",
         "documento": [archivo PDF escaneado]
       }

4. Backend valida:
   ✓ Asistencia es del docente autenticado
   ✓ Estado es AUSENTE o TARDANZA
   ✓ No tiene justificación previa

5. Backend crea justificación:
   └─> Estado cambia: AUSENTE → EN_REVISION
   └─> Guarda documento en: storage/justificaciones/{filename}.pdf

6. Coordinador revisa:
   └─> GET /api/justificaciones (lista pendientes)
   └─> Ve: "Juan Pérez - MAT101-A - 18/11/2025 - Tráfico"
   └─> POST /api/justificaciones/{id}/revisar
   └─> Body: {"aprobada": true, "comentario": "Justificación válida"}

7. Backend actualiza:
   └─> Estado: EN_REVISION → JUSTIFICADO
   └─> Ya no cuenta como ausencia en reportes
```

---

## 📊 **DATOS QUE SE PUEDEN REGISTRAR**

### **✅ Datos Actuales en Asistencia:**

1. **Datos de Identificación:**
   - `id_asignacion_docente` → Quién (docente) + Qué (materia-grupo)
   - `id_horario_clase` → Cuándo (día+bloque) + Dónde (aula)

2. **Datos de Registro:**
   - `fecha_registro` (DATE) → Día exacto
   - `hora_registro` (TIME) → Hora exacta
   - `tipo_registro` (VARCHAR) → BOTON_GPS / QR_VALIDADO / MANUAL_ADMIN

3. **Datos de Estado:**
   - `id_estado` → PRESENTE / TARDANZA / AUSENTE / JUSTIFICADO

4. **Datos Opcionales:**
   - `observacion` (TEXT) → Notas adicionales

---

### **✅ Datos Adicionales que PUEDEN Agregarse (Sin cambiar estructura):**

#### **Opción 1: Usar campo `observacion` (JSON)**
```php
// Guardar metadata en observacion como JSON
$metadata = [
    'coordenadas_gps' => ['lat' => -17.7833, 'long' => -63.1822],
    'distancia_facultad_metros' => 120.5,
    'dispositivo' => 'iPhone 13 Pro',
    'navegador' => 'Safari 16.0',
    'ip_origen' => '192.168.1.10',
    'version_app' => '1.2.3'
];

$asistencia->observacion = json_encode($metadata);
```

**Ventajas:**
- ✅ No requiere migración
- ✅ Flexible para agregar campos
- ✅ Mantiene la estructura actual

**Desventajas:**
- ❌ No se puede indexar
- ❌ No se puede filtrar fácilmente
- ❌ Requiere parseo JSON en queries

---

#### **Opción 2: Agregar columnas específicas (Migración)**
```sql
ALTER TABLE asistencia ADD COLUMN coordenadas_lat DECIMAL(10, 8);
ALTER TABLE asistencia ADD COLUMN coordenadas_long DECIMAL(11, 8);
ALTER TABLE asistencia ADD COLUMN distancia_metros INTEGER;
ALTER TABLE asistencia ADD COLUMN dispositivo VARCHAR(100);
ALTER TABLE asistencia ADD COLUMN ip_origen INET;
```

**Ventajas:**
- ✅ Tipado fuerte
- ✅ Indexable y filtrable
- ✅ Validación a nivel DB

**Desventajas:**
- ❌ Requiere migración
- ❌ Menos flexible para cambios futuros

---

## 🎯 **RECOMENDACIONES PARA DATOS ADICIONALES**

### **Datos ÚTILES para registrar:**

1. **Geolocalización Exacta:**
   ```php
   'coordenadas_lat' => -17.7833,
   'coordenadas_long' => -63.1822,
   'distancia_facultad_metros' => 120
   ```
   **Uso:** Auditoría, detectar fraudes (registros fuera de facultad)

2. **Información del Dispositivo:**
   ```php
   'dispositivo' => 'iPhone 13 Pro - iOS 16.0',
   'navegador' => 'Safari 16.0',
   'ip_origen' => '192.168.1.100'
   ```
   **Uso:** Detectar registros desde múltiples dispositivos simultáneamente

3. **Metadata de QR (si aplica):**
   ```php
   'qr_aula_escaneada' => 15,
   'qr_timestamp_generacion' => '2025-11-18 08:00:00',
   'qr_expiracion' => 300 // segundos
   ```
   **Uso:** Validar que QR no sea reutilizado

4. **Foto Selfie (Opcional - ADVANCED):**
   ```php
   'foto_selfie_url' => '/storage/selfies/2025-11-18_081205_docente1.jpg'
   ```
   **Uso:** Verificación de identidad (anti-suplantación)
   **Consideraciones:** Privacidad, almacenamiento, procesamiento facial

---

## 🚀 **FLUJO PROPUESTO CON DATOS ADICIONALES**

### **Ejemplo Completo de Registro con Metadata:**

```php
// AsistenciaController.php - Método procesarRegistro()

$metadata = [
    'gps' => [
        'lat' => $request->coordenadas['latitud'],
        'long' => $request->coordenadas['longitud'],
        'precision' => $request->coordenadas['precision'] ?? null,
        'distancia_facultad' => $distanciaCalculada
    ],
    'dispositivo' => [
        'user_agent' => $request->header('User-Agent'),
        'ip' => $request->ip(),
        'plataforma' => $request->header('X-Platform') ?? 'unknown' // iOS/Android
    ],
    'qr' => $esQR ? [
        'aula_escaneada' => $request->id_aula_escaneada,
        'aula_esperada' => $horarioClase->id_aula,
        'match' => $request->id_aula_escaneada == $horarioClase->id_aula
    ] : null,
    'tiempo' => [
        'hora_inicio_clase' => $bloqueHorario->hr_inicio,
        'hora_registro' => $horaActual->toTimeString(),
        'minutos_diferencia' => $minutosDif,
        'dentro_ventana' => $dentroVentana
    ]
];

$asistencia = Asistencia::create([
    'id_asignacion_docente' => $horarioClase->id_asignacion_docente,
    'id_horario_clase' => $horarioClase->id_horario_clase,
    'id_estado' => $idEstado,
    'fecha_registro' => $fechaActual,
    'hora_registro' => $horaActual->toTimeString(),
    'tipo_registro' => $tipoRegistro,
    'observacion' => json_encode($metadata) // ← METADATA COMPLETA
]);
```

---

## 📈 **CASOS DE USO DE LOS DATOS ADICIONALES**

### **1. Reporte de Asistencia Detallado:**
```sql
-- Query para detectar posibles fraudes
SELECT 
    a.id_asistencia,
    d.cod_docente,
    p.nombres || ' ' || p.apellidos AS docente,
    a.fecha_registro,
    a.hora_registro,
    a.observacion->>'gps'->>'distancia_facultad' AS distancia,
    a.observacion->>'dispositivo'->>'ip' AS ip
FROM asistencia a
JOIN asignacion_docente ad ON a.id_asignacion_docente = ad.id_asignacion_docente
JOIN docente d ON ad.id_docente = d.id_docente
JOIN perfil_usuario p ON d.id_usuario = p.id_usuario
WHERE (a.observacion->>'gps'->>'distancia_facultad')::INTEGER > 500 -- Más de 500m
  AND a.tipo_registro = 'BOTON_GPS';
```

### **2. Estadísticas de Puntualidad:**
```php
// Calcular promedio de minutos de tardanza por docente
$estadisticas = DB::select("
    SELECT 
        d.cod_docente,
        COUNT(*) as total_registros,
        AVG((observacion->>'tiempo'->>'minutos_diferencia')::INTEGER) as promedio_tardanza,
        MAX((observacion->>'tiempo'->>'minutos_diferencia')::INTEGER) as max_tardanza
    FROM asistencia a
    JOIN asignacion_docente ad ON a.id_asignacion_docente = ad.id_asignacion_docente
    JOIN docente d ON ad.id_docente = d.id_docente
    WHERE a.id_estado IN (3, 4) -- PRESENTE o TARDANZA
    GROUP BY d.cod_docente
");
```

### **3. Detección de Patrones Sospechosos:**
```php
// Detectar registros desde múltiples IPs el mismo día
$sospechosos = DB::select("
    SELECT 
        d.cod_docente,
        a.fecha_registro,
        COUNT(DISTINCT a.observacion->>'dispositivo'->>'ip') as ips_distintas
    FROM asistencia a
    JOIN asignacion_docente ad ON a.id_asignacion_docente = ad.id_asignacion_docente
    JOIN docente d ON ad.id_docente = d.id_docente
    GROUP BY d.cod_docente, a.fecha_registro
    HAVING COUNT(DISTINCT a.observacion->>'dispositivo'->>'ip') > 1
");
```

---

## ✅ **CONCLUSIÓN: ¿SE PUEDE IMPLEMENTAR?**

### **Respuesta: SÍ - El sistema ESTÁ LISTO**

**Lo que ya tienes:**
1. ✅ Estructura de tablas completa
2. ✅ Modelos Eloquent con relaciones
3. ✅ Controladores funcionales
4. ✅ Validaciones de negocio implementadas
5. ✅ Geovalla GPS funcionando
6. ✅ Registro por QR funcionando
7. ✅ Sistema de justificaciones completo
8. ✅ Reportes con estadísticas

**Lo que puedes agregar SIN cambios estructurales:**
1. ✅ Metadata GPS detallada (lat, long, distancia)
2. ✅ Información de dispositivo (user agent, IP, plataforma)
3. ✅ Metadata de QR (aula escaneada, timestamp)
4. ✅ Tiempos exactos (hora inicio, hora registro, diferencia)
5. ✅ Foto selfie (requiere storage adicional)

**Método recomendado:**
- **Usar campo `observacion` con JSON** para datos adicionales
- Ventajas: No requiere migración, flexible, inmediato
- Para queries complejas: crear índices GIN en PostgreSQL para JSON

```sql
CREATE INDEX idx_asistencia_observacion_gin 
ON asistencia USING GIN (observacion jsonb_path_ops);
```

---

## 🎯 **PRÓXIMOS PASOS SUGERIDOS**

1. **Actualizar AsistenciaController** para incluir metadata en `observacion`
2. **Agregar validación de precisión GPS** (rechazar si precisión > 50m)
3. **Implementar endpoint de consulta de historial** con filtros avanzados
4. **Crear dashboard de análisis** para coordinadores
5. **Agregar alertas automáticas** para patrones sospechosos

---

**Documento generado el:** 14 de noviembre de 2025  
**Sistema:** Laravel 12.38.0 + PostgreSQL  
**Estado:** PRODUCCIÓN READY ✅
