-- Trazabilidad candidato/evaluacion para MVP.
-- Anade ORCID como identificador estable sin modificar datos historicos.

USE evaluador_aneca_csyj;
ALTER TABLE evaluaciones
    ADD COLUMN IF NOT EXISTS orcid_candidato VARCHAR(19) NULL DEFAULT NULL AFTER nombre_candidato;
ALTER TABLE evaluaciones
    ADD INDEX IF NOT EXISTS idx_evaluaciones_orcid_candidato_fecha (orcid_candidato, fecha_creacion);

USE evaluador_aneca_experimentales;
ALTER TABLE evaluaciones
    ADD COLUMN IF NOT EXISTS orcid_candidato VARCHAR(19) NULL DEFAULT NULL AFTER nombre_candidato;
ALTER TABLE evaluaciones
    ADD INDEX IF NOT EXISTS idx_evaluaciones_orcid_candidato_fecha (orcid_candidato, fecha_creacion);

USE evaluador_aneca_humanidades;
ALTER TABLE evaluaciones
    ADD COLUMN IF NOT EXISTS orcid_candidato VARCHAR(19) NULL DEFAULT NULL AFTER nombre_candidato;
ALTER TABLE evaluaciones
    ADD INDEX IF NOT EXISTS idx_evaluaciones_orcid_candidato_fecha (orcid_candidato, fecha_creacion);

USE evaluador_aneca_salud;
ALTER TABLE evaluaciones
    ADD COLUMN IF NOT EXISTS orcid_candidato VARCHAR(19) NULL DEFAULT NULL AFTER nombre_candidato;
ALTER TABLE evaluaciones
    ADD INDEX IF NOT EXISTS idx_evaluaciones_orcid_candidato_fecha (orcid_candidato, fecha_creacion);

USE evaluador_aneca_tecnicas;
ALTER TABLE evaluaciones
    ADD COLUMN IF NOT EXISTS orcid_candidato VARCHAR(19) NULL DEFAULT NULL AFTER nombre_candidato;
ALTER TABLE evaluaciones
    ADD INDEX IF NOT EXISTS idx_evaluaciones_orcid_candidato_fecha (orcid_candidato, fecha_creacion);
