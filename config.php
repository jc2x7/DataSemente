<?php
/**
 * Configuração do banco de dados e constantes do sistema
 * Altere os valores abaixo conforme sua hospedagem Locaweb
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'datasemente');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_CHARSET', 'utf8mb4');

define('ROWS_PER_PAGE', 50);
define('MAX_EXPORT_ROWS', 100000);

define('APP_NAME', 'DataSemente');
define('APP_VERSION', '1.0.0');

function getConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
