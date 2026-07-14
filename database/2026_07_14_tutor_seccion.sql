CREATE TABLE IF NOT EXISTS tutor_seccion (
    id_tutor_seccion INT AUTO_INCREMENT PRIMARY KEY,
    id_docente INT NOT NULL,
    id_grado INT NOT NULL,
    id_seccion INT NOT NULL,
    id_periodo INT NOT NULL,
    fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tutor_seccion_periodo (id_grado, id_seccion, id_periodo),
    UNIQUE KEY uq_docente_tutor_periodo (id_docente, id_periodo),
    CONSTRAINT fk_tutor_docente FOREIGN KEY (id_docente) REFERENCES docente(id_docente) ON DELETE CASCADE,
    CONSTRAINT fk_tutor_grado FOREIGN KEY (id_grado) REFERENCES grado(id_grado),
    CONSTRAINT fk_tutor_seccion FOREIGN KEY (id_seccion) REFERENCES seccion(id_seccion),
    CONSTRAINT fk_tutor_periodo FOREIGN KEY (id_periodo) REFERENCES periodo_academico(id_periodo)
);
