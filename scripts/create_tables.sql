-- DataSemente - Schema do banco de dados
-- Execute no phpMyAdmin da Locaweb

CREATE DATABASE IF NOT EXISTS datasemente
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE datasemente;

DROP TABLE IF EXISTS dados_campo;

CREATE TABLE dados_campo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    safra VARCHAR(20),
    especie VARCHAR(150),
    categoria VARCHAR(20),
    cultivar VARCHAR(150),
    municipio VARCHAR(150),
    uf VARCHAR(2),
    status_registro VARCHAR(50),
    data_plantio DATE,
    data_colheita DATE,
    area DECIMAL(12,2),
    producao_bruta DECIMAL(14,2),
    producao_estimada DECIMAL(14,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_safra (safra),
    INDEX idx_especie (especie),
    INDEX idx_categoria (categoria),
    INDEX idx_cultivar (cultivar),
    INDEX idx_uf (uf),
    INDEX idx_municipio (municipio),
    INDEX idx_status (status_registro),
    INDEX idx_safra_especie (safra, especie),
    INDEX idx_uf_especie (uf, especie),
    INDEX idx_safra_uf (safra, uf),
    INDEX idx_safra_cultivar (safra, cultivar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
