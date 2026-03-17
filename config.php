<?php
/**
 * Configuração do banco de dados - DataSemente
 * Altere os valores conforme sua hospedagem Locaweb
 */

define('DB_HOST', 'crmagrbr.mysql.dbaas.com.br');
define('DB_NAME', 'crmagrbr');
define('DB_USER', 'crmagrbr');
define('DB_PASS', 'RRQ9QJ6qwgf!');
define('DB_CHARSET', 'utf8mb4');

define('ROWS_PER_PAGE', 50);
define('MAX_EXPORT_ROWS', 100000);

define('APP_NAME', 'DataSemente');
define('APP_VERSION', '2.0.0');

function getConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * Helper para construir WHERE clause a partir dos filtros
 */
function buildFilters(array $get): array
{
    $where = [];
    $params = [];

    if (!empty($get['safra'])) {
        $safras = is_array($get['safra']) ? $get['safra'] : [$get['safra']];
        $placeholders = implode(',', array_fill(0, count($safras), '?'));
        $where[] = "safra IN ($placeholders)";
        $params = array_merge($params, $safras);
    }

    if (!empty($get['especie'])) {
        $especies = is_array($get['especie']) ? $get['especie'] : [$get['especie']];
        $placeholders = implode(',', array_fill(0, count($especies), '?'));
        $where[] = "especie IN ($placeholders)";
        $params = array_merge($params, $especies);
    }

    if (!empty($get['categoria'])) {
        $categorias = is_array($get['categoria']) ? $get['categoria'] : [$get['categoria']];
        $placeholders = implode(',', array_fill(0, count($categorias), '?'));
        $where[] = "categoria IN ($placeholders)";
        $params = array_merge($params, $categorias);
    }

    if (!empty($get['cultivar'])) {
        if (is_array($get['cultivar'])) {
            $placeholders = implode(',', array_fill(0, count($get['cultivar']), '?'));
            $where[] = "cultivar IN ($placeholders)";
            $params = array_merge($params, $get['cultivar']);
        } else {
            $where[] = 'cultivar LIKE ?';
            $params[] = '%' . $get['cultivar'] . '%';
        }
    }

    if (!empty($get['uf'])) {
        $ufs = is_array($get['uf']) ? $get['uf'] : [$get['uf']];
        $placeholders = implode(',', array_fill(0, count($ufs), '?'));
        $where[] = "uf IN ($placeholders)";
        $params = array_merge($params, $ufs);
    }

    if (!empty($get['municipio'])) {
        $municipios = is_array($get['municipio']) ? $get['municipio'] : [$get['municipio']];
        $placeholders = implode(',', array_fill(0, count($municipios), '?'));
        $where[] = "municipio IN ($placeholders)";
        $params = array_merge($params, $municipios);
    }

    if (!empty($get['status'])) {
        $statuses = is_array($get['status']) ? $get['status'] : [$get['status']];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $where[] = "status_registro IN ($placeholders)";
        $params = array_merge($params, $statuses);
    }

    if (!empty($get['busca'])) {
        $where[] = '(especie LIKE ? OR cultivar LIKE ? OR municipio LIKE ?)';
        $term = '%' . $get['busca'] . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    return [$clause, $params];
}
