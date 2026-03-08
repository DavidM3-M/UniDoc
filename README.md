# Sistema de Cualificación Docentes — API REST

> API backend desarrollada en **Laravel 11** para gestionar el proceso completo de cualificación, postulación, aval, contratación y seguimiento de docentes universitarios.

---

## Tabla de Contenidos

1. [Descripción del Proyecto](#descripción-del-proyecto)
2. [Tecnologías](#tecnologías)
3. [Requisitos Previos](#requisitos-previos)
4. [Instalación](#instalación)
5. [Configuración](#configuración)
6. [Autenticación](#autenticación)
7. [Roles del Sistema](#roles-del-sistema)
8. [Estructura de Endpoints](#estructura-de-endpoints)
   - [Auth](#auth)
   - [Aspirante](#aspirante)
   - [Docente](#docente)
   - [Talento Humano](#talento-humano)
   - [Administrador](#administrador)
   - [Coordinador](#coordinador)
   - [Rectoría](#rectoría)
   - [Vicerrectoría](#vicerrectoría)
   - [Apoyo Profesoral](#apoyo-profesoral)
   - [Evaluador de Producción](#evaluador-de-producción)
   - [Producción Académica — Catálogos](#producción-académica--catálogos)
   - [Público](#público)
   - [Ubicaciones](#ubicaciones)
   - [Constantes](#constantes)
   - [Notificaciones](#notificaciones)
9. [Formatos de Respuesta](#formatos-de-respuesta)
10. [Colección Postman](#colección-postman)
11. [Flujo de Trabajo del Sistema](#flujo-de-trabajo-del-sistema)
12. [Flujo de Rechazo](#flujo-de-rechazo)
13. [Errores Verificados y Corregidos](#errores-verificados-y-corregidos)
14. [Mejoras de Lógica de Negocio](#mejoras-de-lógica-de-negocio)

---

## Descripción del Proyecto

El sistema gestiona el ciclo de vida completo de la vinculación docente universitaria:

1. El **aspirante** se registra, completa su hoja de vida (estudios, idiomas, experiencias, producción académica, documentos) y postula a convocatorias publicadas por Talento Humano.
2. **Talento Humano** crea convocatorias, revisa postulaciones y otorga avales.
3. **El Coordinador** realiza evaluaciones del proceso de aprobación.
4. **Vicerrectoría** y **Rectoría** otorgan avales de instancias superiores.
5. **Talento Humano** contrata al aspirante aprobado, cambiando su rol a **Docente**.
6. **Apoyo Profesoral** filtra y analiza docentes por competencias.
7. **Evaluador de Producción** revisa y aprueba/rechaza documentos de producción académica.

En cada paso del flujo el sistema envía **notificaciones automáticas** (correo electrónico + base de datos) a los actores relevantes: publicación de convocatoria, confirmación de postulación, cambio de estado, contratación y transición de avales entre instancias.

---

## Tecnologías

| Componente | Detalle |
|---|---|
| Framework | Laravel 11 |
| Lenguaje | PHP 8.2+ |
| Autenticación | JWT (`php-open-source-saver/jwt-auth`) |
| Roles y permisos | `spatie/laravel-permission` |
| Base de datos | MySQL / PostgreSQL |
| Exportación Excel | `maatwebsite/excel` |
| PDF | `barryvdh/laravel-dompdf` |
| Documentación API | L5-Swagger (OpenAPI 3.0) |
| Notificaciones | Laravel Notifications (`database` + correo electrónico) |
| Correo | Laravel Mail + Mailable (`NotificacionMail`) |
| Imágenes | `intervention/image` |

---

## Requisitos Previos

- PHP >= 8.2
- Composer >= 2.x
- Node.js >= 18 (para assets con Vite)
- MySQL >= 8 o PostgreSQL >= 14
- Extensiones PHP: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `gd`, `fileinfo`

---

## Instalación

```bash
# 1. Clonar el repositorio
git clone <repo-url>
cd Desarrollo_Cualificacion_Docentes

# 2. Instalar dependencias PHP
composer install

# 3. Instalar dependencias JS
npm install

# 4. Copiar variables de entorno
cp .env.example .env

# 5. Generar clave de aplicación
php artisan key:generate

# 6. Generar secreto JWT
php artisan jwt:secret

# 7. Correr migraciones y seeders
php artisan migrate --seed

# 8. Enlace de almacenamiento
php artisan storage:link

# 9. Iniciar servidor de desarrollo
php artisan serve
```

---

## Configuración

Variables de entorno requeridas en `.env`:

```dotenv
APP_NAME="Cualificacion Docentes"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cualificacion_docentes
DB_USERNAME=root
DB_PASSWORD=secret

JWT_SECRET=<generado con jwt:secret>
JWT_TTL=1440          # minutos (24 horas)

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="noreply@universidad.edu.co"
```

---

## Autenticación

La API usa **JWT (JSON Web Token)**. Pasos:

1. Registrar usuario → `POST /api/auth/registrar-usuario`
2. Iniciar sesión → `POST /api/auth/iniciar-sesion` → Recibe `token`
3. Enviar el token en todas las rutas protegidas:
   ```
   Authorization: Bearer <token>
   ```
4. Cerrar sesión → `POST /api/auth/cerrar-sesion` (invalida el token)

---

## Roles del Sistema

| Rol | Descripción |
|---|---|
| `Aspirante` | Usuario recién registrado; puede completar su HV y postular a convocatorias |
| `Docente` | Aspirante contratado; accede a sus evaluaciones y puntaje |
| `Talento Humano` | Gestiona convocatorias, postulaciones, avales y contrataciones |
| `Administrador` | Gestión completa de usuarios, roles y normativas |
| `Coordinador` | Evalúa aspirantes en el proceso de aprobación |
| `Vicerrectoria` | Otorga avales de segunda instancia; revisa documentos |
| `Rectoria` | Otorga avales de instancia máxima; revisión final de documentos |
| `Apoyo Profesoral` | Consulta y filtra docentes por competencias para asignación |
| `Evaluador Produccion` | Aprueba o rechaza documentos de producción académica |

---

## Estructura de Endpoints

> **URL base:** `http://localhost:8000/api`
>
> Todos los endpoints (salvo los marcados como públicos) requieren `Authorization: Bearer <token>`.

---

### Auth

**Prefijo:** `/auth`

> **Rate limiting (seguridad):** Los endpoints públicos tienen límite de intentos por minuto por IP:
> - `registrar-usuario` e `iniciar-sesion`: **10 req/min**
> - `restablecer-contrasena` y `restablecer-contrasena-token`: **5 req/min**

| Método | URI | Auth | Rate limit | Descripción |
|--------|-----|------|------------|-------------|
| POST | `/auth/registrar-usuario` | No | 10/min | Registra un nuevo usuario con rol Aspirante |
| POST | `/auth/iniciar-sesion` | No | 10/min | Inicia sesión y retorna el JWT |
| POST | `/auth/restablecer-contrasena` | No | 5/min | Solicita restablecimiento (respuesta genérica para no revelar si el correo existe) |
| POST | `/auth/restablecer-contrasena-token` | No | 5/min | Actualiza contraseña usando el token del email (token comparado con hash SHA-256) |
| POST | `/auth/cerrar-sesion` | Sí | — | Invalida el JWT del usuario en sesión |
| GET | `/auth/obtener-usuario-autenticado` | Sí | — | Retorna datos del usuario autenticado |
| POST | `/auth/actualizar-contrasena` | Sí | — | Actualiza contraseña del usuario autenticado (sin ID en URL; usa el token) |
| POST | `/auth/actualizar-usuario` | Sí | — | Actualiza el perfil del usuario autenticado |

#### `POST /auth/registrar-usuario` — Cuerpo

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `nombre` | string | Sí | Nombres del usuario |
| `apellido` | string | Sí | Apellidos del usuario |
| `correo` | string (email) | Sí | Correo electrónico único |
| `contrasena` | string | Sí | Contraseña (mínimo 8 caracteres) |
| `tipo_documento` | string | Sí | Tipo de documento (CC, CE, etc.) |
| `numero_documento` | string | Sí | Número de documento único |

**Respuesta `201`:**
```json
{
  "message": "Usuario registrado exitosamente",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "usuario": {
    "id": 1,
    "nombre": "Juan",
    "apellido": "Pérez",
    "correo": "juan@example.com",
    "rol": "Aspirante"
  }
}
```

#### `POST /auth/iniciar-sesion` — Cuerpo

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `correo` | string | Sí | Correo registrado |
| `contrasena` | string | Sí | Contraseña |

**Respuesta `200`:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 86400
}
```

---

### Aspirante

**Prefijo:** `/aspirante` | **Middleware:** `auth:api`, `role:Aspirante`

#### Perfil y Foto

| Método | URI | Descripción |
|--------|-----|-------------|
| POST | `/aspirante/crear-foto-perfil` | Sube foto de perfil (jpeg/png/jpg, máx 2 MB) |
| GET | `/aspirante/obtener-foto-perfil` | Retorna URL de la foto de perfil |
| DELETE | `/aspirante/eliminar-foto-perfil` | Elimina la foto de perfil |

#### RUT

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-rut` | Obtiene el RUT del usuario |
| POST | `/aspirante/crear-rut` | Crea el RUT (multipart/form-data con archivo PDF) |
| PUT | `/aspirante/actualizar-rut` | Actualiza el RUT |

**Campos `POST /aspirante/crear-rut` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `archivo_rut` | file (PDF) | Sí | Archivo del RUT |
| `tipo_persona` | string | Sí | Tipo de persona (catálogo `/constantes/tipo-persona`) |
| `codigo_ciiu` | string | Sí | Código CIIU (catálogo `/constantes/codigo-ciiu`) |
| `numero_documento` | string | Sí | Número de documento |

#### Información de Contacto

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-informacion-contacto` | Obtiene información de contacto |
| POST | `/aspirante/crear-informacion-contacto` | Crea información de contacto |
| PUT | `/aspirante/actualizar-informacion-contacto` | Actualiza información de contacto |

**Campos `POST /aspirante/crear-informacion-contacto` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `direccion` | string | Sí | Dirección de residencia |
| `telefono` | string | Sí | Número de teléfono |
| `municipio_id` | integer | Sí | ID del municipio |
| `categoria_libreta_militar` | string | No | Categoría libreta militar |
| `numero_libreta_militar` | string | No | Número libreta militar |
| `distrito_militar` | string | No | Distrito militar |
| `archivo_libreta_militar` | file (PDF) | No | Archivo libreta militar |

#### EPS

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-eps` | Obtiene registro de EPS |
| POST | `/aspirante/crear-eps` | Crea registro de EPS (form-data con archivo) |
| PUT | `/aspirante/actualizar-eps` | Actualiza EPS |

**Campos `POST /aspirante/crear-eps` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `nombre_eps` | string | Sí | Nombre de la EPS |
| `estado_afiliacion` | string | Sí | Estado de afiliación |
| `tipo_afiliacion` | string | Sí | Tipo de afiliación |
| `tipo_afiliado` | string | Sí | Tipo de afiliado |
| `archivo_eps` | file (PDF) | Sí | Documento de afiliación a EPS |

#### Pensión

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-pension` | Obtiene registro de pensión |
| POST | `/aspirante/crear-pension` | Crea registro de pensión (form-data con archivo) |
| PUT | `/aspirante/actualizar-pension` | Actualiza pensión |

**Campos `POST /aspirante/crear-pension` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `nombre_fondo` | string | Sí | Nombre del fondo de pensión |
| `regimen_pensional` | string | Sí | Régimen pensional (catálogo `/constantes/tipos-pension`) |
| `archivo_pension` | file (PDF) | Sí | Certificado de vinculación pensional |

#### ARL

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-arl` | Obtiene registro de ARL |
| POST | `/aspirante/crear-arl` | Crea registro de ARL (form-data con archivo) |
| PUT | `/aspirante/actualizar-arl` | Actualiza ARL |

#### Certificación Bancaria

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-certificacion-bancaria` | Obtiene certificación bancaria |
| POST | `/aspirante/crear-certificacion-bancaria` | Crea certificación bancaria (form-data con archivo) |
| PUT | `/aspirante/actualizar-certificacion-bancaria` | Actualiza certificación bancaria |

**Campos `POST /aspirante/crear-certificacion-bancaria` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `banco` | string | Sí | Nombre del banco |
| `numero_cuenta` | string | Sí | Número de cuenta |
| `tipo_cuenta` | string | Sí | Tipo de cuenta (catálogo `/constantes/tipos-cuenta-bancaria`) |
| `archivo_certificacion` | file (PDF) | Sí | Certificado bancario |

#### Antecedentes Judiciales

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-antecedentes-judiciales` | Obtiene antecedentes judiciales |
| POST | `/aspirante/crear-antecedentes-judiciales` | Crea antecedentes judiciales (form-data con archivo) |
| PUT | `/aspirante/actualizar-antecedentes-judiciales` | Actualiza antecedentes judiciales |

#### Idiomas

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-idiomas` | Lista todos los idiomas del usuario |
| GET | `/aspirante/obtener-idioma/{id}` | Obtiene un idioma por ID |
| POST | `/aspirante/crear-idioma` | Agrega un idioma (form-data con certificado) |
| PUT | `/aspirante/actualizar-idioma/{id}` | Actualiza un idioma |
| DELETE | `/aspirante/eliminar-idioma/{id}` | Elimina un idioma |

**Campos `POST /aspirante/crear-idioma` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `idioma` | string | Sí | Nombre del idioma |
| `nivel` | string | Sí | Nivel (catálogo `/constantes/niveles-idioma`) |
| `institucion` | string | Sí | Institución que expidió el certificado |
| `fecha_expedicion` | date (Y-m-d) | Sí | Fecha de expedición |
| `archivo_certificado` | file (PDF) | Sí | Certificado del idioma |

#### Experiencias Laborales

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-experiencias` | Lista experiencias del usuario |
| GET | `/aspirante/obtener-experiencia/{id}` | Obtiene experiencia por ID |
| POST | `/aspirante/crear-experiencia` | Agrega experiencia (form-data con certificado) |
| PUT | `/aspirante/actualizar-experiencia/{id}` | Actualiza experiencia |
| DELETE | `/aspirante/eliminar-experiencia/{id}` | Elimina experiencia |

**Campos `POST /aspirante/crear-experiencia` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `empresa` | string | Sí | Nombre de la empresa/institución |
| `cargo` | string | Sí | Cargo desempeñado |
| `tipo_experiencia` | string | Sí | Tipo (catálogo `/constantes/tipos-experiencia`) |
| `fecha_inicio` | date (Y-m-d) | Sí | Fecha de inicio |
| `fecha_fin` | date (Y-m-d) | No | Fecha de fin (vacío si trabajo actual) |
| `municipio_id` | integer | Sí | ID del municipio |
| `descripcion` | string | No | Descripción de funciones |
| `archivo_certificado` | file (PDF) | Sí | Certificado laboral |

#### Estudios / Títulos Académicos

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-estudios` | Lista estudios del usuario |
| GET | `/aspirante/obtener-estudio/{id}` | Obtiene estudio por ID |
| POST | `/aspirante/crear-estudio` | Agrega estudio (form-data con diploma) |
| PUT | `/aspirante/actualizar-estudio/{id}` | Actualiza estudio |
| DELETE | `/aspirante/eliminar-estudio/{id}` | Elimina estudio |

**Campos `POST /aspirante/crear-estudio` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `institucion` | string | Sí | Institución educativa |
| `titulo` | string | Sí | Título obtenido |
| `tipo_estudio` | string | Sí | Tipo (catálogo `/constantes/tipos-estudio`) |
| `perfil_profesional` | string | Sí | Perfil (catálogo `/constantes/perfiles-profesionales`) |
| `fecha_grado` | date (Y-m-d) | Sí | Fecha de grado |
| `municipio_id` | integer | Sí | ID del municipio de la institución |
| `archivo_diploma` | file (PDF) | Sí | Diploma o acta de grado |

#### Producción Académica

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-producciones` | Lista producciones académicas |
| GET | `/aspirante/obtener-produccion/{id}` | Obtiene producción por ID |
| POST | `/aspirante/crear-produccion` | Agrega producción (form-data con archivo) |
| PUT | `/aspirante/actualizar-produccion/{id}` | Actualiza producción |
| DELETE | `/aspirante/eliminar-produccion/{id}` | Elimina producción |

**Campos `POST /aspirante/crear-produccion` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `titulo` | string | Sí | Título de la producción |
| `id_producto_academico` | integer | Sí | ID del tipo de producto (catálogo) |
| `id_ambito_divulgacion` | integer | Sí | ID del ámbito de divulgación (catálogo) |
| `anio` | integer | Sí | Año de publicación |
| `descripcion` | string | No | Descripción adicional |
| `archivo_produccion` | file (PDF) | Sí | Documento que certifica la producción |

#### Aptitudes

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-aptitudes` | Lista aptitudes del usuario |
| GET | `/aspirante/obtener-aptitud/{id}` | Obtiene aptitud por ID |
| POST | `/aspirante/crear-aptitud` | Agrega aptitud |
| PUT | `/aspirante/actualizar-aptitud/{id}` | Actualiza aptitud |
| DELETE | `/aspirante/eliminar-aptitud/{id}` | Elimina aptitud |

#### Convocatorias y Postulaciones

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/ver-convocatorias` | Lista convocatorias abiertas |
| GET | `/aspirante/ver-convocatoria/{id}` | Detalle de convocatoria |
| POST | `/aspirante/crear-postulacion/{convocatoriaId}` | Postula a una convocatoria |
| GET | `/aspirante/ver-postulaciones` | Lista mis postulaciones |
| DELETE | `/aspirante/eliminar-postulacion/{id}` | Elimina mi postulación |

**Validaciones automáticas en `POST /aspirante/crear-postulacion/{convocatoriaId}`:**

| Requisito | Lógica aplicada |
|-----------|----------------|
| **Experiencia** | Se suman los años de **todas** las experiencias del aspirante (sin filtrar por tipo). El total debe ser ≥ a la suma de todos los años requeridos en `requisitos_experiencia`. |
| **Idiomas** | Se aplica lógica de **mínimos MCER**. Si la convocatoria exige nivel A2 y el aspirante acredita C1 en cualquier idioma, el requisito se considera cumplido. Para el formato asociativo `{"inglés":"B2"}` se valida el idioma específico; para el formato de lista `["B1"]` basta con que cualquier idioma registrado tenga nivel ≥ al requerido. |
| **Estudios** | El aspirante debe tener al menos un estudio académico registrado. |
| **Estado** | La convocatoria debe estar en estado `Abierta`. |

#### Normativas

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/aspirante/obtener-normativas` | Lista normativas vigentes |
| GET | `/aspirante/obtener-normativa/{id}` | Detalle de normativa |

---

### Docente

**Prefijo:** `/docente` | **Middleware:** `auth:api`, `role:Docente`

Contiene los mismos endpoints de gestión de HV que el Aspirante, más los siguientes exclusivos:

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/docente/ver-contratacion` | Obtiene la contratación del docente autenticado |
| POST | `/docente/crear-evaluacion` | Crea la evaluación docente inicial |
| GET | `/docente/ver-evaluaciones` | Ve su propia evaluación |
| PUT | `/docente/actualizar-evaluacion` | Actualiza la evaluación docente |
| GET | `/docente/evaluar-puntaje` | Calcula y guarda el puntaje total del docente |

---

### Talento Humano

**Prefijo:** `/talentoHumano` | **Middleware:** `auth:api`, `role:Talento Humano`

#### Convocatorias

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/talentoHumano/obtener-convocatorias` | Lista todas las convocatorias |
| GET | `/talentoHumano/obtener-convocatoria/{id}` | Detalle de convocatoria |
| GET | `/talentoHumano/obtener-tipos-cargos` | Catálogo de tipos de cargo |
| POST | `/talentoHumano/crear-convocatoria` | Crea convocatoria (form-data con PDF) |
| PUT | `/talentoHumano/actualizar-convocatoria/{id}` | Actualiza convocatoria |
| DELETE | `/talentoHumano/eliminar-convocatoria/{id}` | Elimina convocatoria |
| GET | `/talentoHumano/exportar-convocatorias-excel` | Exporta convocatorias a Excel |

**Campos `POST /talentoHumano/crear-convocatoria` (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `titulo` | string | Sí | Título de la convocatoria |
| `descripcion` | string | Sí | Descripción del cargo |
| `tipo_cargo_id` | integer | Sí | ID del tipo de cargo |
| `fecha_inicio` | date | Sí | Inicio de la convocatoria |
| `fecha_fin` | date | Sí | Cierre de la convocatoria |
| `requisitos_idiomas` | JSON object / array | No | Ver formatos abajo |
| `requisitos_experiencia` | JSON object | No | `{"Docencia Universitaria": 2, "Investigación": 1}` — la clave es el tipo y el valor los años mínimos. Al postular se valida la **suma total** de todos los valores. |

**Formatos aceptados para `requisitos_idiomas`:**

| Formato | Ejemplo | Comportamiento |
|---------|---------|----------------|
| Objeto asociativo | `{"inglés": "B2", "francés": "A2"}` | Se verifica idioma + nivel mínimo por idioma específico. |
| Array de niveles | `["B1", "A2"]` | Se verifica que el aspirante tenga **al menos un idioma** con nivel ≥ al requerido por cada entrada (sin restricción de idioma específico). |
| `archivo_convocatoria` | file (PDF) | Sí | Documento oficial |

#### Experiencias Requeridas

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/talentoHumano/experiencias-requeridas` | Lista plantillas |
| POST | `/talentoHumano/experiencias-requeridas` | Crea plantilla |
| GET | `/talentoHumano/experiencias-requeridas/{id}` | Detalle |
| PUT | `/talentoHumano/experiencias-requeridas/{id}` | Actualiza |
| DELETE | `/talentoHumano/experiencias-requeridas/{id}` | Elimina |

#### Postulaciones

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/talentoHumano/obtener-postulaciones` | Lista todas las postulaciones |
| PUT | `/talentoHumano/actualizar-postulacion/{idPostulacion}` | Actualiza estado (puede rechazar con motivo) |
| DELETE | `/talentoHumano/eliminar-postulacion/{idPostulacion}` | Elimina postulación |
| GET | `/talentoHumano/hoja-de-vida-pdf/{idConvocatoria}/{idUsuario}` | Genera PDF de HV |

**Campos `PUT /talentoHumano/actualizar-postulacion/{idPostulacion}` (JSON):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `estado_postulacion` | string | Sí | `Enviada`, `Faltan documentos`, `Rechazada`, `Aprobada` |
| `motivo_rechazo` | string | **Sí si `Rechazada`** | Motivo del rechazo (ej: `"Falta de documentos"`, `"Perfil no cumple requisitos"`) |

#### Contrataciones

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/talentoHumano/obtener-contrataciones` | Lista contrataciones |
| GET | `/talentoHumano/obtener-contratacion/{id_contratacion}` | Detalle |
| POST | `/talentoHumano/crear-contratacion/{user_id}` | Contrata aspirante (requiere avales, cambia rol a Docente) |
| PUT | `/talentoHumano/actualizar-contratacion/{id_contratacion}` | Actualiza contratación |
| DELETE | `/talentoHumano/eliminar-contratacion/{id}` | Elimina contratación (revierte rol a Aspirante) |

**Campos `POST /talentoHumano/crear-contratacion/{user_id}` (JSON):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `convocatoria_id` | integer | Sí | ID de la convocatoria |
| `fecha_inicio` | date | Sí | Inicio del contrato |
| `fecha_fin` | date | Sí | Fin del contrato |
| `tipo_contrato` | string | Sí | Tipo de contrato |
| `salario` | number | Sí | Salario pactado |
| `observaciones` | string | No | Observaciones adicionales |

**Respuesta `201`:**
```json
{
  "message": "Contratación creada exitosamente. El usuario ha sido vinculado como Docente.",
  "contratacion": {
    "id": 5,
    "user_id": 12,
    "convocatoria_id": 3,
    "fecha_inicio": "2026-02-01",
    "fecha_fin": "2026-12-31",
    "estado": "Activo"
  }
}
```

#### Avales

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/talentoHumano/avales` | Lista avales (filtros: `convocatoria_id`, `user_id`) |
| POST | `/talentoHumano/avales` | Crea / actualiza aval |
| PUT | `/talentoHumano/avales/{id}` | Aprueba o rechaza aval |
| POST | `/talento-humano/rechazar-aval/{userId}` | Rechaza perfil del aspirante (cadena de avales) |

**Campos `PUT /talentoHumano/avales/{id}` (JSON):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `estado` | string | Sí | `pending`, `aprobado`, `rechazado` |
| `motivo_rechazo` | string | **Sí si `rechazado`** | Motivo del rechazo |

**Campos `POST /talento-humano/rechazar-aval/{userId}` (JSON):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `motivo_rechazo` | string | Sí | Razón del rechazo. Se notifica al aspirante y se marcan sus postulaciones activas como `Rechazada` |

---

### Administrador

**Prefijo:** `/admin` | **Middleware:** `auth:api`, `role:Administrador`

#### Usuarios

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/admin/listar-usuarios` | Lista todos los usuarios con roles |
| PUT | `/admin/editar-usuario/{id}` | Edita datos de usuario |
| DELETE | `/admin/eliminar-usuario/{id}` | Elimina usuario |
| PUT | `/admin/usuarios/{id}/cambiar-rol` | Cambia el rol de un usuario |
| GET | `/admin/usuarios/exportar-excel` | Exporta usuarios a Excel |

#### Roles

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/admin/listar-roles` | Lista todos los roles |
| POST | `/admin/asignar-rol` | Asigna rol a usuario |
| POST | `/admin/remover-rol/{id}` | Remueve rol de usuario |
| PUT | `/admin/actualizar-rol/{id}` | Actualiza nombre de rol |

#### Normativas

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/admin/obtener-normativas` | Lista normativas |
| GET | `/admin/obtener-normativa/{id}` | Detalle de normativa |
| POST | `/admin/crear-normativa` | Crea normativa |
| PUT | `/admin/actualizar-normativa/{id}` | Actualiza normativa |
| DELETE | `/admin/eliminar-normativa/{id}` | Elimina normativa |

#### Otros

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/admin/usuarios-excel` | Reporte completo de usuarios en Excel |
| POST | `/admin/uploadCsv` | Carga CSV de países/departamentos/municipios |

#### Aspirantes (también accesible por Vicerrectoría, Rectoría y Talento Humano)

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/admin/aspirantes` | Lista aspirantes |
| GET | `/admin/aspirantes/estadisticas` | Estadísticas de aspirantes |
| GET | `/admin/aspirantes/{id}` | Detalle de aspirante |
| GET | `/admin/aspirantes/{id}/hoja-vida-pdf` | HV del aspirante en PDF |
| POST | `/admin/aspirantes/{id}/dar-aval` | Otorga aval al aspirante |

---

### Coordinador

**Prefijo:** `/coordinador` | **Middleware:** `auth:api`, `role:Coordinador`

#### Evaluaciones

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/coordinador/evaluaciones` | Lista evaluaciones |
| GET | `/coordinador/evaluaciones-con-usuarios` | Evaluaciones con detalle del usuario |
| POST | `/coordinador/evaluaciones` | Crea evaluación de aprobación |
| GET | `/coordinador/evaluaciones/{id}` | Obtiene evaluación por ID de usuario |
| PUT | `/coordinador/evaluaciones/{id}` | Actualiza evaluación |

**Campos `POST /coordinador/evaluaciones` (JSON):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `user_id` | integer | Sí | ID del aspirante evaluado |
| `convocatoria_id` | integer | Sí | ID de la convocatoria |
| `resultado_psicotecnico` | string | Sí | Resultado de prueba psicotécnica |
| `resultado_clase` | string | Sí | Resultado de clase de muestra |
| `resultado_validacion` | string | Sí | Resultado de validación de documentos |
| `aprobado` | boolean | Sí | Si el aspirante es aprobado |
| `observaciones` | string | No | Notas adicionales |

#### Documentos

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/coordinador/obtener-documentos/{estado}` | Documentos por estado (`pendiente`, `aprobado`, `rechazado`) |
| PUT | `/coordinador/actualizar-documento/{id}` | Actualiza estado de documento (requiere motivo si `rechazado`) |
| GET | `/coordinador/listar-docentes` | Lista docentes |
| GET | `/coordinador/ver-documentos-docente/{id}` | Documentos de un docente |
| GET | `/coordinador/ver-documento/{id}` | Ver documento |
| GET | `/coordinador/documentos/{userId}/{categoria}` | Documentos por categoría |

#### Plantillas y Otros

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/coordinador/plantillas` | Lista plantillas de evaluación |
| POST | `/coordinador/plantillas` | Crea plantilla |
| GET | `/coordinador/plantillas/{id}` | Ver plantilla |
| PUT | `/coordinador/plantillas/{id}` | Actualiza plantilla |
| GET | `/coordinador/aspirantes` | Aspirantes aprobados por TH |
| GET | `/coordinador/aspirantes/{id}` | Detalle de aspirante |
| GET | `/coordinador/postulaciones` | Postulaciones por convocatoria |
| GET | `/coordinador/convocatorias` | Convocatorias con aspirantes |

#### Avales

| Método | URI | Descripción |
|--------|-----|-------------|
| POST | `/coordinador/aval-hoja-vida/{userId}` | Otorga aval del Coordinador |
| POST | `/coordinador/rechazar-aval/{userId}` | Rechaza el perfil (requiere aval de TH previo) |
| GET | `/coordinador/usuarios/{userId}/avales` | Avales de un usuario |
| GET | `/coordinador/usuarios` | Lista usuarios aprobados por TH |

---

### Rectoría

**Prefijo:** `/rectoria` | **Middleware:** `auth:api`, `role:Rectoria`

| Método | URI | Descripción |
|--------|-----|-------------|
| POST | `/rectoria/aval-hoja-vida/{userId}` | Otorga aval de Rectoría (requiere aval previo de Vicerrectoría) |
| POST | `/rectoria/rechazar-aval/{userId}` | Rechaza el perfil (requiere aval de Vicerrectoría previo) |
| GET | `/rectoria/usuarios/{userId}/avales` | Avales de un usuario |
| GET | `/rectoria/usuarios` | Usuarios aprobados por Vicerrectoría |
| GET | `/rectoria/usuarios-convocatorias` | Usuarios con avales y sus convocatorias |
| GET | `/rectoria/obtener-documentos/{estado}` | Documentos por estado |
| PUT | `/rectoria/actualizar-documento/{id}` | Actualiza estado de documento (requiere motivo si `rechazado`) |
| GET | `/rectoria/listar-docentes` | Lista docentes |
| GET | `/rectoria/ver-documentos-docente/{id}` | Documentos de docente |
| GET | `/rectoria/ver-documento/{id}` | Ver documento |
| GET | `/rectoria/documentos/{userId}/{categoria}` | Documentos por categoría |
| GET | `/rectoria/convocatorias` | Convocatorias con aspirantes |
| GET | `/rectoria/obtener-convocatorias` | Lista convocatorias |
| GET | `/rectoria/obtener-convocatoria/{id}` | Detalle de convocatoria |
| GET | `/rectoria/hoja-de-vida-pdf/{idUsuario}` | Genera PDF de HV |
| GET | `/rectoria/evaluaciones/{userId}` | Evaluación del coordinador para usuario |
| GET | `/rectoria/evaluaciones-con-usuarios` | Todas las evaluaciones con datos del usuario |

---

### Vicerrectoría

**Prefijo:** `/vicerrectoria` | **Middleware:** `auth:api`, `role:Vicerrectoria`

| Método | URI | Descripción |
|--------|-----|-------------|
| POST | `/vicerrectoria/aval-hoja-vida/{userId}` | Otorga aval de Vicerrectoría (requiere avales de TH y Coordinador) |
| POST | `/vicerrectoria/rechazar-aval/{userId}` | Rechaza el perfil (requiere avales de TH y Coordinador previos) |
| GET | `/vicerrectoria/usuarios/{userId}/avales` | Avales de usuario |
| GET | `/vicerrectoria/usuarios` | Lista usuarios |
| GET | `/vicerrectoria/obtener-documentos/{estado}` | Documentos por estado |
| PUT | `/vicerrectoria/actualizar-documento/{id}` | Actualiza estado de documento (requiere motivo si `rechazado`) |
| GET | `/vicerrectoria/listar-docentes` | Lista docentes |
| GET | `/vicerrectoria/ver-documentos-docente/{id}` | Documentos de docente |
| GET | `/vicerrectoria/ver-documento/{id}` | Ver documento |
| GET | `/vicerrectoria/documentos/{userId}/{categoria}` | Documentos por categoría |
| GET | `/vicerrectoria/obtener-postulaciones` | Todas las postulaciones |
| GET | `/vicerrectoria/usuarios/{userId}/postulaciones` | Postulaciones de un usuario |
| GET | `/vicerrectoria/obtener-convocatorias` | Lista convocatorias |
| GET | `/vicerrectoria/obtener-convocatoria/{id}` | Detalle de convocatoria |
| GET | `/vicerrectoria/hoja-de-vida-pdf/{idUsuario}` | PDF de HV |
| GET | `/vicerrectoria/evaluaciones/{userId}` | Evaluación del coordinador |
| GET | `/vicerrectoria/evaluaciones-con-usuarios` | Evaluaciones con datos de usuario |

---

### Apoyo Profesoral

**Prefijo:** `/apoyoProfesoral` | **Middleware:** `auth:api`, `role:Apoyo Profesoral`

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/apoyoProfesoral/obtener-documentos/{estado}` | Documentos por estado |
| PUT | `/apoyoProfesoral/actualizar-documento/{id}` | Actualiza estado de documento |
| GET | `/apoyoProfesoral/listar-docentes` | Lista todos los docentes |
| GET | `/apoyoProfesoral/ver-documentos-docente/{id}` | Documentos de docente |
| GET | `/apoyoProfesoral/mostrar-todos-estudios` | Docentes con estudios |
| GET | `/apoyoProfesoral/filtrar-docentes-estudio/{tipo}` | Filtrar por tipo de estudio |
| GET | `/apoyoProfesoral/filtrar-docentes-estudio-id/{id}` | Estudios de un docente |
| GET | `/apoyoProfesoral/mostrar-todos-idioma` | Docentes con idiomas |
| GET | `/apoyoProfesoral/filtrar-docentes-idioma/{idioma}` | Filtrar por nivel de idioma |
| GET | `/apoyoProfesoral/filtrar-docentes-idioma-id/{id}` | Idiomas de un docente |
| GET | `/apoyoProfesoral/mostrar-todos-produccion` | Docentes con producción académica |
| GET | `/apoyoProfesoral/filtrar-docentes-produccion/{id}` | Producciones de un docente |
| GET | `/apoyoProfesoral/filtrar-docentes-ambito/{ambitoId}` | Filtrar por ámbito de divulgación |
| GET | `/apoyoProfesoral/mostrar-todas-experiencia` | Docentes con experiencias |
| GET | `/apoyoProfesoral/filtrar-docentes-experiencia-id/{id}` | Experiencias de un docente |
| GET | `/apoyoProfesoral/filtrar-docentes-tipo-experiencia/{tipo}` | Filtrar por tipo de experiencia |
| POST | `/apoyoProfesoral/crear-certificados-masivos` | Genera certificados en masa |

---

### Evaluador de Producción

**Prefijo:** `/evaluadorProduccion` | **Middleware:** `auth:api`, `role:Evaluador Produccion`

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/evaluadorProduccion/obtener-producciones` | Usuarios con documentos de producción pendientes |
| GET | `/evaluadorProduccion/ver-producciones-por-usuario/{user_id}` | Producciones pendientes de un usuario |
| PUT | `/evaluadorProduccion/actualizar-produccion/{documento_id}` | Aprueba o rechaza documento de producción |

**Campos `PUT /evaluadorProduccion/actualizar-produccion/{documento_id}` (JSON):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `estado` | string | Sí | `Aprobado` o `Rechazado` |
| `observaciones` | string | No | Justificación |

---

### Producción Académica — Catálogos

**Prefijo:** `/tiposProduccionAcademica` | Sin autenticación

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/tiposProduccionAcademica/productos-academicos` | Tipos de productos académicos |
| GET | `/tiposProduccionAcademica/ambitos-divulgacion` | Todos los ámbitos de divulgación |
| GET | `/tiposProduccionAcademica/ambitos_divulgacion/{id_producto_academico}` | Ámbitos para un tipo de producto |
| GET | `/tiposProduccionAcademica/ambito-divulgacion-completo/{id_ambito_divulgacion}` | Info completa de ámbito + producto |

---

### Público

**Prefijo:** `/publico` | Sin autenticación

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/publico/convocatorias` | Lista pública de convocatorias activas |
| GET | `/publico/convocatorias/{id}` | Detalle público de una convocatoria |

**Respuesta `GET /publico/convocatorias` `200`:**
```json
{
  "convocatorias": [
    {
      "id": 1,
      "titulo": "Convocatoria Docente 2026-I",
      "descripcion": "Docente de ingeniería de sistemas...",
      "fecha_inicio": "2026-01-15",
      "fecha_fin": "2026-02-28",
      "estado": "Abierta"
    }
  ]
}
```

---

### Ubicaciones

**Prefijo:** `/ubicaciones` | Sin autenticación

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/ubicaciones/paises` | Lista todos los países |
| GET | `/ubicaciones/departamentos` | Lista todos los departamentos |
| GET | `/ubicaciones/departamentos/{pais_id}` | Departamentos de un país |
| GET | `/ubicaciones/municipios/{departamento_id}` | Municipios de un departamento |
| GET | `/ubicaciones/municipio/{municipio_id}` | Información completa de un municipio |

---

### Notificaciones

**Prefijo:** ninguno (rutas raíz) | **Middleware:** `auth:api` (cualquier rol autenticado)

Permite a cualquier usuario autenticado consultar y gestionar sus propias notificaciones. Las notificaciones se generan automáticamente por eventos del sistema (nueva convocatoria, cambio de estado de postulación, contratación, etc.).

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/notificaciones` | Lista todas las notificaciones del usuario autenticado, ordenadas de más reciente a más antigua |
| PUT | `/notificaciones/{id}/leer` | Marca una notificación específica como leída |
| PUT | `/notificaciones/leer-todas` | Marca todas las notificaciones no leídas como leídas |

**Respuesta `GET /notificaciones` `200`:**
```json
{
  "notificaciones": [
    {
      "id": "uuid",
      "mensaje": "Se ha publicado una nueva convocatoria en el sistema UniDoc.",
      "leida": false,
      "created_at": "2026-03-05T14:30:00.000000Z"
    }
  ]
}
```

**Eventos que disparan notificaciones automáticas:**

| Evento | Canal | Destinatarios |
|--------|-------|---------------|
| Nueva convocatoria publicada | DB + Email | Todos los Aspirantes y Docentes |
| Aspirante se postula | DB + Email | TH (aviso) + Aspirante (confirmación) |
| Cambio de estado de postulación | DB + Email | El aspirante postulado |
| **Postulación rechazada** | DB + Email | El aspirante (con motivo y quién rechazó) |
| Nueva contratación | DB + Email | El usuario contratado |
| Aval de TH aprobado | DB + Email | Coordinadores |
| Aval de Coordinación aprobado | DB + Email | Vicerrectoría |
| Aval de Vicerrectoría aprobado | DB + Email | Rectoría |
| Aval final de Rectoría completado | DB + Email | El aspirante |
| **Aval rechazado (TH / Coord / VR / Rectoría)** | DB + Email | El aspirante (con etapa y motivo) |
| **Documento rechazado** | DB + Email | El propietario del documento (con motivo) |

---

### Constantes

**Prefijo:** `/constantes` | Sin autenticación

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/constantes/tipos-documento` | Tipos de documento (CC, CE, TI, etc.) |
| GET | `/constantes/estado-civil` | Opciones de estado civil |
| GET | `/constantes/genero` | Opciones de género |
| GET | `/constantes/tipo-persona` | Tipos de persona para RUT |
| GET | `/constantes/codigo-ciiu` | Códigos CIIU para RUT |
| GET | `/constantes/estado-afiliacion` | Estados de afiliación EPS |
| GET | `/constantes/tipo-afiliacion` | Tipos de afiliación EPS |
| GET | `/constantes/tipo-afiliado` | Tipos de afiliado EPS |
| GET | `/constantes/categoria-libreta-militar` | Categorías de libreta militar |
| GET | `/constantes/tipos-estudio` | Tipos de estudio |
| GET | `/constantes/perfiles-profesionales` | Perfiles profesionales |
| GET | `/constantes/tipos-experiencia` | Tipos de experiencia laboral |
| GET | `/constantes/niveles-idioma` | Niveles de idioma (A1–C2) |
| GET | `/constantes/tipos-cuenta-bancaria` | Tipos de cuenta bancaria |
| GET | `/constantes/tipos-pension` | Regímenes pensionales |

---

## Formatos de Respuesta

### Exitosa

```json
{ "message": "Operación exitosa", "data": { ... } }
```

### Error de validación `422`

```json
{
  "message": "Los datos proporcionados no son válidos.",
  "errors": { "campo": ["El campo es obligatorio."] }
}
```

### No autenticado `401`

```json
{ "message": "Unauthenticated." }
```

### Sin permiso `403`

```json
{ "message": "No tienes permisos para realizar esta acción." }
```

### Recurso no encontrado `404`

```json
{ "message": "Recurso no encontrado." }
```

---

## Colección Postman

Se incluye el archivo `postman_collection.json` en la raíz del proyecto.

**Importar en Postman:**

1. Abrir Postman → **Import** → **Upload Files**
2. Seleccionar `postman_collection.json`
3. En **Environments**, crear una variable `base_url` = `http://localhost:8000/api`
4. Tras iniciar sesión, guardar el token retornado en la variable `token`

Todas las peticiones protegidas ya incluyen el header:
```
Authorization: Bearer {{token}}
```

---

## Flujo de Trabajo del Sistema

```
Registro (Aspirante)
       │
       ▼
Completar HV (estudios, idiomas, experiencias, producción, documentos)
       │
       ▼
Postulación a Convocatoria
       │
       ▼
Revisión Talento Humano ──► Aval TH ──────────────────────────┐
       │                                            Rechazar ──► Notificación al Aspirante
       ▼                                                        (motivo + correo + DB)
Evaluación Coordinador ──► Aval Coordinador ──────────────────┤
       │                                            Rechazar ──┤
       ▼                                                        │
Revisión Vicerrectoría ──► Aval Vicerrectoría ────────────────┤
       │                                            Rechazar ──┤
       ▼                                                        │
Revisión Rectoría ──► Aval Rectoría ──────────────────────────┘
       │
       ▼
Contratación (TH) ──► Rol cambia a Docente
       │
       ▼
Gestión Docente + Apoyo Profesoral + Evaluador Producción
```

---

## Flujo de Rechazo

El sistema implementa un flujo completo de rechazo en tres niveles. En cada caso se envían notificaciones automáticas (correo + base de datos) al aspirante con el motivo y la instancia que rechazó.

### 1 — Rechazo de postulación por Talento Humano

**Endpoint:** `PUT /talentoHumano/actualizar-postulacion/{idPostulacion}`

```json
{
  "estado_postulacion": "Rechazada",
  "motivo_rechazo": "Falta de documentos obligatorios"
}
```

- El campo `motivo_rechazo` es **obligatorio** cuando `estado_postulacion = "Rechazada"`.
- El estado queda registrado en `postulacions.estado_postulacion` junto con `motivo_rechazo` y `rechazado_por` (rol del usuario que rechazó).
- Se envía notificación al aspirante indicando el motivo y quién rechazó.

---

### 2 — Rechazo en la cadena de avales (perfil/hoja de vida)

Cada rol cuenta con un endpoint dedicado de rechazo. Al rechazar se marcan las postulaciones activas del aspirante como `Rechazada` y se notifica al aspirante.

| Endpoint | Rol | Prerequisito |
|----------|-----|---------------|
| `POST /talento-humano/rechazar-aval/{userId}` | Talento Humano | Ninguno |
| `POST /coordinador/rechazar-aval/{userId}` | Coordinador | Requiere aval de TH |
| `POST /vicerrectoria/rechazar-aval/{userId}` | Vicerrectoría | Requiere avales de TH y Coordinador |
| `POST /rectoria/rechazar-aval/{userId}` | Rectoría | Requiere aval de Vicerrectoría |

**Body (todos):**
```json
{ "motivo_rechazo": "El aspirante no cumple con el perfil requerido" }
```

---

### 3 — Rechazo de aval por convocatoria (`convocatoria_avales`)

**Endpoint:** `PUT /talentoHumano/avales/{id}`

```json
{
  "estado": "rechazado",
  "motivo_rechazo": "Documentación de soporte no válida"
}
```

- El `motivo_rechazo` (o `comentario`) es obligatorio cuando `estado = "rechazado"`.
- Solo el usuario con el rol que coincide con el campo `aval` del registro puede rechazar.
- Se notifica al aspirante con el nombre de la etapa y el motivo.

---

### 4 — Rechazo de documentos

**Endpoints disponibles por rol:**

| Rol | Endpoint |
|-----|----------|
| Talento Humano | *(sin endpoint propio de documentos)* |
| Coordinador | `PUT /coordinador/actualizar-documento/{id}` |
| Vicerrectoría | `PUT /vicerrectoria/actualizar-documento/{id}` |
| Rectoría | `PUT /rectoria/actualizar-documento/{id}` |
| Apoyo Profesoral | `PUT /apoyoProfesoral/actualizar-documento/{id}` |

**Body:**
```json
{
  "estado": "rechazado",
  "motivo_rechazo": "El documento está vencido o es ilegible"
}
```

- El `motivo_rechazo` es **obligatorio** cuando `estado = "rechazado"`.
- Se resuelve automáticamente el propietario del documento (via relación polimórfica) y se le envía notificación con motivo y rol que rechazó.
- El campo `motivo_rechazo` queda persistido en la tabla `documentos` para consulta posterior.

---

### Motivos de rechazo predefinidos (sugeridos)

El campo `motivo_rechazo` es texto libre. Se recomiendan valores estandarizados como:

- `"Falta de documentos"` — documentos requeridos no cargados
- `"Documento vencido o ilegible"` — archivo inválido
- `"Perfil no cumple requisitos"` — experiencia/estudios insuficientes
- `"No aprobó evaluación"` — clase de muestra o prueba psicotécnica no aprobada
- `"Criterios institucionales no cumplidos"` — decisión institucional

---

## Errores Verificados y Corregidos

Los siguientes errores fueron detectados mediante revisión del código fuente y corregidos directamente en los archivos correspondientes.

---

### BUG-001 — Ruta duplicada en `routes/aval.php` (Rectoría)

**Archivo:** `routes/aval.php`  
**Gravedad:** Media — la segunda definición de la ruta sobrescribe silenciosamente a la primera en Laravel, lo que puede provocar comportamientos inesperados ante futuras modificaciones.

**Causa:** Se definió dos veces seguidas la misma ruta dentro del grupo `role:Rectoria`:
```php
// Antes (duplicado)
Route::get('convocatorias', [ProcesoAprobacionController::class, 'listarConvocatoriasConAspirantes']);
// Convocatorias con aspirantes
Route::get('convocatorias', [ProcesoAprobacionController::class, 'listarConvocatoriasConAspirantes']);
```

**Corrección:** Se eliminó la línea duplicada. La ruta queda definida una sola vez:
```php
// Después (corregido)
Route::get('convocatorias', [ProcesoAprobacionController::class, 'listarConvocatoriasConAspirantes']);
```

---

### BUG-002 — Typo en el nombre de la URI de Apoyo Profesoral

**Archivo:** `routes/apoyo_profesoral.php`  
**Gravedad:** Alta — cualquier cliente que usara la URL documentada (`filtrar-docentes-tipo-experiencia`) recibiría un `404`.

**Causa:** El nombre del segmento de ruta tenía una letra transpuesta: `experiecnia` en lugar de `experiencia`.
```php
// Antes (typo)
Route::get('filtrar-docentes-tipo-experiecnia/{tipo}', [...]);
```

**Corrección:**
```php
// Después (corregido)
Route::get('filtrar-docentes-tipo-experiencia/{tipo}', [...]);
```

**Nota:** Si existen clientes ya integrados con la URI incorrecta, deben actualizar su URL.

---

### BUG-003 — README documenta ruta inexistente `POST /auth/actualizar-contrasena/{id}`

**Archivo:** `README.md` (documentación), `routes/auth.php` (código fuente)  
**Gravedad:** Media — podría llevar a integraciones incorrectas del frontend.

**Causa:** El README indicaba que la ruta recibía un `{id}` de usuario externo. La implementación real opera sobre el usuario **autenticado** en la sesión; no acepta ningún ID en la URL.
```
// Antes (incorrecto en README)
POST /auth/actualizar-contrasena/{id}

// Real (en routes/auth.php)
Route::post('actualizar-contrasena', [AuthController::class, 'actualizarContrasena']);
```

**Corrección:** Se actualizó la tabla de endpoints en el README:
```
POST /auth/actualizar-contrasena   — Actualiza contraseña del usuario autenticado
```

---

### BUG-004 — README documenta rutas de Administrador que no están implementadas

**Archivo:** `README.md` (documentación), `routes/admin.php` (código fuente)  
**Gravedad:** Baja — genera confusión pero no afecta el runtime.

**Causa:** El README listaba `POST /admin/crear-rol` y `DELETE /admin/eliminar-rol` como endpoints disponibles. En `routes/admin.php` solo existe el comentario explícito: _"crearRol y eliminarRol están deshabilitados (métodos no implementados)"_. Los métodos no existen en `RoleController`.

**Corrección:** Se eliminaron esas dos filas de la tabla de Roles en el README. Los endpoints activos son:

| Método | URI | Descripción |
|--------|-----|-------------|
| GET | `/admin/listar-roles` | Lista todos los roles |
| POST | `/admin/asignar-rol` | Asigna rol a usuario |
| POST | `/admin/remover-rol/{id}` | Remueve rol de usuario |
| PUT | `/admin/actualizar-rol/{id}` | Actualiza nombre de rol |

---

---

### MEJORA-001 — Validación de experiencia: suma total en lugar de por tipo

**Archivo:** `app/Http/Controllers/TalentoHumano/PostulacionController.php`  
**Método:** `verificarRequisitosExperienciaEspecifica`

**Cambio:** Antes se verificaba cada tipo de experiencia de forma independiente (el aspirante debía cumplir X años por cada tipo listado). Ahora se suman **todos los años requeridos** y se comparan contra el **total de años de experiencia del aspirante** sin distinción de tipo.

```php
// Antes — por tipo
foreach ($requisitosExperiencia as $tipo => $anosRequeridos) {
    $anosUsuario = calcularAniosExperienciaPorTipo($tipo);
    if ($anosUsuario < $anosRequeridos) throw new Exception(...);
}

// Ahora — suma total
$totalAnosRequeridos = array_sum($requisitosExperiencia); // suma de todos los valores
$totalAnosUsuario   = totalDias / 365.25;               // toda la experiencia
if ($totalAnosUsuario < $totalAnosRequeridos) throw new Exception(...);
```

---

### MEJORA-002 — Validación de idiomas: mínimos MCER y soporte array indexado

**Archivo:** `app/Http/Controllers/TalentoHumano/PostulacionController.php`  
**Método:** `verificarRequisitosIdiomas`

**Cambio:** Se elimina el error 500 ante `requisitos_idiomas` en formato de array indexado y se implementa lógica de **niveles mínimos** para ambos formatos:

- **Array indexado** `["A2", "B1"]`: el aspirante debe tener al menos un idioma registrado con nivel ≥ al requerido por cada entrada. Un C1 satisface un requisito A2.
- **Objeto asociativo** `{"inglés": "B2"}`: se valida idioma específico con nivel ≥ al requerido (comportamiento previo, ya usaba `compararNivelesIdioma`).

Adicionalmnete se corrigió un bug en `ConvocatoriaController` (create y update) donde un array indexado no se persistía por falso positivo de `empty()` sobre el índice `0`.

---

## Licencia

Este proyecto es propiedad de la institución. Uso restringido a personal autorizado.

