-- =====================================================================
-- MIGRAÇÃO — agendamento de rega por DIA DA SEMANA
-- Rode uma vez no banco GoFarmingBD já existente.
-- =====================================================================
USE GoFarmingBD;

-- Cabia só VARCHAR(500) e a foto em base64 estourava. (mantido da migração anterior)
ALTER TABLE Plantas MODIFY COLUMN foto_url LONGTEXT;

-- Quantas vezes por semana (1 a 7) — número, não texto.
ALTER TABLE Plantas
    ADD COLUMN vezes_por_semana TINYINT NOT NULL DEFAULT 2 AFTER frequencia_rega;

-- Dias da semana escolhidos, formato ISO-8601: 1=Seg ... 7=Dom. Ex: "1,4"
ALTER TABLE Plantas
    ADD COLUMN dias_semana VARCHAR(20) NOT NULL DEFAULT '1,4' AFTER vezes_por_semana;

-- Impede duas regas para a mesma planta no mesmo dia (causava linha duplicada
-- no dashboard por causa do LEFT JOIN, e notificação repetida).
ALTER TABLE Regas
    ADD UNIQUE KEY uq_planta_data (id_planta, data_prevista);

-- Índice para a consulta do cron/sino.
ALTER TABLE Regas
    ADD INDEX idx_data_status (data_prevista, status);
