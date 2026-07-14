# Matriz de requerimientos funcionales

Estado usado:
- Cumple: tiene pantalla, JS y API con persistencia en la BD del dump.
- Parcial: existe parte del flujo, pero falta una funcion clave.
- Pendiente: falta una pieza importante.

| Codigo | Requerimiento | Estado | Implementacion actual | Pendiente recomendado |
| --- | --- | --- | --- | --- |
| RF-01 | Mantenimiento de Usuarios | Parcial | `modulos/usuarios.html`, `js/usuarios.js`, `api/usuarios.php` registran, editan y eliminan usuarios. | Agregar activar/inactivar como accion explicita sin borrar historial. |
| RF-02 | Mantenimiento de Estudiantes | Cumple parcial | `modulos/estudiantes.html`, `js/estudiantes.js`, `api/estudiantes.php`. | Evitar eliminacion dura cuando tenga matriculas/notas/asistencia; preferir estado. |
| RF-03 | Mantenimiento de Docentes | Cumple | `modulos/docentes.html`, `js/docentes.js`, `api/docentes.php` registran docentes y asignan curso en `curso_docente`. | Soportar multiples cursos por docente desde UI si se requiere. |
| RF-04 | Gestion de Cursos | Cumple | `modulos/cursos.html`, `js/cursos.js`, `api/cursos.php` crean, modifican y asignan docentes. | Agregar eliminacion/inactivacion desde UI. |
| RF-05 | Gestion de Aulas | Cumple | `modulos/aulas.html`, `js/aulas.js`, `api/aulas.php`. | Bloquear cambios si el aula tiene horarios en uso. |
| RF-06 | Gestion de Roles | Parcial | `modulos/roles.html`, `js/roles.js`, `api/roles.php`. | Crear tabla/flujo de permisos por rol; hoy los permisos viven en `funcion.js`. |
| RF-07 | Gestion de Documentos | Cumple | `modulos/documentos.html`, `js/documentos.js`, `api/documentos.php`, tabla `documento` y `tipo_documento`. | Mejorar seguridad de descargas con endpoint protegido. |
| RF-08 | Registro de Notas | Cumple | `modulos/notas.html`, `js/notas.js`, `api/notas.php`, tabla `nota`. | Agregar carga masiva por curso/seccion si se requiere. |
| RF-09 | Reporte de Notas | Parcial | El listado de notas muestra reporte basico filtrable visualmente. | Generar PDF/Excel por estudiante, curso y periodo. |
| RF-10 | Registro de Matriculas | Cumple parcial | `modulos/matricula.html`, `js/matriculas.js`, `api/matriculas.php` registran matriculas y actualizan vacantes. | Validar duplicidad de matricula activa por estudiante/periodo. |
| RF-11 | Ficha de Matricula | Cumple | `modulos/ficha.html`, `js/ficha.js`, `api/ficha.php` generan ficha con estudiante, apoderados y pagos. | Exportar a PDF formal. |
| RF-12 | Pasarela de Pagos | Parcial | `modulos/pagos.html`, `js/pagos.js`, `api/pagos.php` registran pagos y marcan pagado. | Integrar pasarela real si el pago sera en linea. |
| RF-13 | Generacion de Comprobantes | Cumple parcial | `api/comprobantes.php` genera comprobante imprimible y registra en `comprobante`; `js/pagos.js` abre comprobante. | Convertir impresion HTML a PDF persistente si se necesita archivo. |
| RF-14 | Registro de Asistencia | Cumple | `modulos/asistencia.html`, `js/asistencia.js`, `api/asistencia.php`, tabla `asistencia`. | Agregar carga por lista de estudiantes en una sola pantalla. |
| RF-15 | Gestion de Apoderados | Cumple parcial | `modulos/apoderados.html`, `js/apoderados.js`, `api/apoderados.php` registran, editan y asocian apoderados. | Soportar multiples estudiantes por apoderado desde UI. |
| RF-16 | Gestion de Horarios | Cumple | `modulos/horarios.html`, `js/horarios.js`, `api/horarios.php`, tabla `horario`; valida cruce de aula. | Validar tambien cruce de docente. |
| RF-17 | Gestion de Vacantes | Cumple | `modulos/vacantes.html`, `js/vacantes.js`, `api/vacantes.php`; matriculas descuentan/devuelven cupos. | Bloquear disponibles mayores al total en API. |
| RF-18 | Reporte de Asistencia | Parcial | `api/asistencia.php?action=obtener` entrega historial para reporte en tabla. | Crear vista estadistica/PDF por fecha, curso y estudiante. |
| RF-19 | Gestion de Incidencias | Cumple | `modulos/incidencias.html`, `js/incidencias.js`, `api/incidencias.php`, tabla `incidencia`. | Agregar estados de seguimiento si el negocio lo requiere. |
| RF-20 | Reporte de Incidencias | Parcial | El historial de incidencias se muestra desde BD. | Agregar filtros y exportacion PDF/Excel. |

## Ajustes hechos contra la BD

- Se usaron las tablas reales del dump: `nota`, `asistencia`, `horario`, `incidencia`, `documento`, `tipo_documento`, `tipo_incidencia`, `periodo_evaluacion`, `comprobante`.
- Los catalogos vacios `periodo_evaluacion`, `tipo_incidencia` y `tipo_documento` se inicializan con valores basicos desde sus APIs.
- El estado "pagado" de pagos se detecta por nombre en `estado_pago`, porque en la BD el registro pagado es `id_estado_pago = 4`.

## Prioridad sugerida

1. Cambiar eliminaciones duras por estados activo/inactivo en entidades con historial.
2. Convertir reportes parciales a PDF/Excel: notas, asistencia e incidencias.
3. Llevar permisos de menu desde `funcion.js` hacia permisos por rol en BD.
4. Revisar duplicados y datos de prueba en la BD (`estado_pago` tiene dos pendientes, usuarios repetidos y cursos duplicados).
