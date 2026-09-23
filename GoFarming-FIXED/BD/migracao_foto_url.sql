-- Rode isso se o banco GoFarmingBD já existe (evita recriar tudo).
-- Corrige a coluna foto_url, que estava VARCHAR(500) e não cabia
-- a imagem em base64 salva pelo app (causava truncamento/erro no salvar).
USE GoFarmingBD;

ALTER TABLE Plantas MODIFY COLUMN foto_url LONGTEXT;
