-- Adiciona coluna de horário de rega na tabela Plantas
-- Execute este script se já tiver o banco criado (migração incremental)
ALTER TABLE Plantas
    ADD COLUMN IF NOT EXISTS horario_rega VARCHAR(5) NOT NULL DEFAULT '08:00'
    COMMENT 'Horário preferido de rega no formato HH:MM';
