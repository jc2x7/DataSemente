<?php
/**
 * API de opcoes de filtros dependentes (cascata)
 * Retorna municipios/cultivares/especies filtrados pelos filtros ativos
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();

    // Build WHERE from active filters (excluding the field we're querying)
    $results = [];

    // Municipios filtrados por UF (e outros filtros ativos)
    {
        $get = $_GET;
        unset($get['municipio']); // don't filter municipio by itself
        [$where, $params] = buildFilters($get);
        $sql = "SELECT DISTINCT municipio FROM dados_campo $where ORDER BY municipio";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['municipios'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Cultivares filtradas por UF, municipio, especie, safra, categoria
    {
        $get = $_GET;
        unset($get['cultivar']); // don't filter cultivar by itself
        [$where, $params] = buildFilters($get);
        $sql = "SELECT DISTINCT cultivar FROM dados_campo $where ORDER BY cultivar";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['cultivares'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Especies filtradas pelos outros filtros
    {
        $get = $_GET;
        unset($get['especie']);
        [$where, $params] = buildFilters($get);
        $sql = "SELECT DISTINCT especie FROM dados_campo $where ORDER BY especie";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results['especies'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode(['success' => true, 'options' => $results], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
