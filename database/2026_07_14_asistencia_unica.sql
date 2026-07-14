ALTER TABLE asistencia
    ADD UNIQUE KEY uq_asistencia_estudiante_curso_fecha (id_estudiante, id_curso_docente, fecha);
