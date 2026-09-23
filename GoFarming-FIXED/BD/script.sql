CREATE DATABASE IF NOT EXISTS GoFarmingBD;
USE GoFarmingBD;

CREATE TABLE Usuarios (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100) NOT NULL,
    email     VARCHAR(150) NOT NULL UNIQUE,
    senha     VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Plantas (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario          INT NOT NULL,
    nome                VARCHAR(100) NOT NULL,
    especie             VARCHAR(150),
    foto_url            LONGTEXT,
    frequencia_rega     VARCHAR(100),
    vezes_por_semana    TINYINT NOT NULL DEFAULT 2,
    dias_semana         VARCHAR(20) NOT NULL DEFAULT '1,4',
    horario_rega        VARCHAR(5) NOT NULL DEFAULT '08:00',
    access_token_plantid VARCHAR(255),
    criada_em           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id) ON DELETE CASCADE
);

CREATE TABLE Regas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    id_planta     INT NOT NULL,
    data_prevista DATE NOT NULL,
    data_regada   DATETIME DEFAULT NULL,
    status        ENUM('pendente','concluida') DEFAULT 'pendente',
    UNIQUE KEY uq_planta_data (id_planta, data_prevista),
    INDEX idx_data_status (data_prevista, status),
    FOREIGN KEY (id_planta) REFERENCES Plantas(id) ON DELETE CASCADE
);

CREATE TABLE Notificacoes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_planta  INT DEFAULT NULL,
    tipo       VARCHAR(30) NOT NULL DEFAULT 'rega',
    titulo     VARCHAR(150) NOT NULL,
    mensagem   VARCHAR(255) NOT NULL,
    lida       TINYINT(1) NOT NULL DEFAULT 0,
    criada_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_planta)  REFERENCES Plantas(id)  ON DELETE CASCADE
);
