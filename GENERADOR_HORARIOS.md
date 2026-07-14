# Generador automatico de horarios por seccion

## Objetivo

El generador crea el horario semanal completo de una sola seccion por vez. Por ejemplo, primero se genera 1ro A, luego 1ro B, y asi sucesivamente.

La generacion es independiente por seccion, pero respeta conflictos globales de profesor y aula.

## Flujo desde admin

1. Ingresar como administrador.
2. Abrir el modulo `Horarios`.
3. Seleccionar `Grado` y `Seccion`.
4. Presionar `Generar horario automatico`.
5. Revisar la vista previa y los logs.
6. Presionar `Guardar vista previa`.

El boton `Regenerar horario de esta seccion` vuelve a calcular la vista previa. Al guardar, reemplaza solo el horario del grado/seccion seleccionado.

El boton `Revertir horario de esta seccion` restaura la generacion anterior guardada para ese grado/seccion. Si no existe una generacion anterior, elimina el horario actual solo de esa seccion.

## Horario base

- Dias: Lunes a Viernes.
- Rango: 08:15 a 14:15.
- Refrigerio fijo: 11:15 a 11:45.
- Bloques de clase:
  - 08:15 a 09:45
  - 09:45 a 11:15
  - 11:45 a 13:15

Ninguna clase supera 90 minutos y ninguna clase dura menos de 90 minutos. Por eso el tramo 13:15 a 14:15 queda libre para actividades internas, salida, tutorias cortas o uso administrativo, pero no para clases curriculares.

## Cursos base

El API asegura que existan estos cursos antes de generar:

- Lenguaje
- Literatura
- Algebra
- Trigonometria
- Fisica
- Quimica
- Educacion Fisica
- Historia
- Geografia
- Ingles

Si algun curso no tiene docente asignado en `curso_docente`, el sistema lo asigna a docentes existentes de forma rotativa para permitir la generacion inicial.

## Algoritmo

El generador usa una estrategia greedy con prioridad:

1. Educacion Fisica.
2. Cursos principales: Lenguaje, Algebra, Fisica, Quimica e Ingles.
3. Cursos secundarios.

Para cada curso pendiente:

1. Recorre dias de Lunes a Viernes.
2. Recorre bloques disponibles, saltando el refrigerio.
3. Valida disponibilidad del profesor.
4. Valida que la seccion no tenga otra clase.
5. Valida que el aula no este ocupada.
6. Si todo cumple, asigna el bloque.
7. Si no se puede asignar, registra un log.

## Validaciones aplicadas

- Profesor sin cruce global.
- Seccion sin dos clases simultaneas.
- Aula sin cruce global.
- Horario dentro de 08:15 a 14:15.
- Bloques de 90 minutos.
- No se permite cruzar el refrigerio.
- Educacion Fisica debe tener al menos una clase semanal de 90 minutos.
- Un docente no puede dictar mas de 3 horas el mismo dia en la misma seccion.
- Un docente no puede dictar mas de 3 horas el mismo dia en la misma aula.

## Tablas usadas

Tablas existentes:

- `horario`
- `grado`
- `seccion`
- `curso`
- `curso_docente`
- `docente`
- `aula`
- `usuario`
- `periodo_academico`

Estructura agregada por el generador:

- `horario.id_generacion`
- `horario_generacion`
- `horario_generacion_detalle`
- `horario_generacion_log`
- `docente_disponibilidad`

La tabla `docente_disponibilidad` es opcional. Si un docente no tiene filas registradas, se considera disponible en todo el horario base. Si tiene filas, solo se asigna dentro de esos rangos.

`horario` contiene el horario vigente. `horario_generacion` y `horario_generacion_detalle` conservan el historial de cada generacion guardada.

## Asignacion a estudiantes

El horario no se duplica por cada estudiante. Cada bloque se guarda con `id_grado` e `id_seccion` en la tabla `horario`.

Cuando un alumno inicia sesion, el modulo de horarios filtra automaticamente:

- `horario.id_grado = estudiante.id_grado`
- `horario.id_seccion = estudiante.id_seccion`

Por eso, todo estudiante activo que pertenezca a la seccion generada ve su horario sin que sea necesario crear registros repetidos por alumno.

## API principal

Archivo:

- `api/horario_generador.php`

Acciones:

- `combo`: carga grados y secciones.
- `preview`: genera vista previa sin guardar.
- `guardar`: guarda la vista previa y reemplaza solo el horario de la seccion elegida.

## Ejemplo de resultado

Ejemplo para una seccion:

| Dia | Hora | Curso |
| --- | --- | --- |
| Lunes | 08:15 - 09:45 | Educacion Fisica |
| Lunes | 09:45 - 11:15 | Lenguaje |
| Lunes | 11:15 - 11:45 | Refrigerio |
| Lunes | 11:45 - 13:15 | Algebra |
| Lunes | 13:15 - 14:15 | Ingles |

El horario real depende de los docentes, aulas y conflictos existentes en la base de datos.
