USE db_barberia;

-- Conserva los registros existentes; haz una copia de seguridad antes de ejecutarla.
SELECT COUNT(*) INTO @has_fecha_nacimiento
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'fecha_nacimiento';
SET @migration_sql = IF(
    @has_fecha_nacimiento = 0,
    'ALTER TABLE usuarios ADD COLUMN fecha_nacimiento DATE NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SELECT COUNT(*) INTO @has_notas
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'notas';
SET @migration_sql = IF(
    @has_notas = 0,
    'ALTER TABLE usuarios ADD COLUMN notas TEXT NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SELECT COUNT(*) INTO @has_estado_servicio
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'servicios' AND column_name = 'estado';
SET @migration_sql = IF(
    @has_estado_servicio = 0,
    'ALTER TABLE servicios ADD COLUMN estado ENUM(''Activo'', ''Inactivo'') NOT NULL DEFAULT ''Activo''',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
