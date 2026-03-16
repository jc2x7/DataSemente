-- Criação do banco de dados e tabela principal
-- Execute este script no phpMyAdmin da Locaweb antes de importar os dados

CREATE DATABASE IF NOT EXISTS datasemente
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE datasemente;

DROP TABLE IF EXISTS dados_campo;

CREATE TABLE dados_campo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    safra VARCHAR(20),
    cultura VARCHAR(100),
    cultivar VARCHAR(150),
    estado VARCHAR(2),
    municipio VARCHAR(150),
    regiao VARCHAR(100),
    area_plantada DECIMAL(12,2),
    produtividade DECIMAL(12,2),
    producao DECIMAL(14,2),
    data_plantio DATE,
    data_colheita DATE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_safra (safra),
    INDEX idx_cultura (cultura),
    INDEX idx_estado (estado),
    INDEX idx_municipio (municipio),
    INDEX idx_cultivar (cultivar),
    INDEX idx_cultura_estado (cultura, estado),
    INDEX idx_safra_cultura (safra, cultura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
