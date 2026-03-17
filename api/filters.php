<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();

    $safras = $pdo->query("SELECT DISTINCT safra FROM dados_campo WHERE safra IS NOT NULL ORDER BY safra DESC")
                  ->fetchAll(PDO::FETCH_COLUMN);

    $especies = $pdo->query("SELECT DISTINCT especie FROM dados_campo WHERE especie IS NOT NULL ORDER BY especie")
                    ->fetchAll(PDO::FETCH_COLUMN);

    $categorias = $pdo->query("SELECT DISTINCT categoria FROM dados_campo WHERE categoria IS NOT NULL ORDER BY categoria")
                      ->fetchAll(PDO::FETCH_COLUMN);

    $estados = $pdo->query("SELECT DISTINCT uf FROM dados_campo WHERE uf IS NOT NULL ORDER BY uf")
                   ->fetchAll(PDO::FETCH_COLUMN);

    $statuses = $pdo->query("SELECT DISTINCT status_registro FROM dados_campo WHERE status_registro IS NOT NULL ORDER BY status_registro")
                    ->fetchAll(PDO::FETCH_COLUMN);

    $cultivares = $pdo->query("SELECT DISTINCT cultivar FROM dados_campo WHERE cultivar IS NOT NULL ORDER BY cultivar")
                      ->fetchAll(PDO::FETCH_COLUMN);

    $stats = $pdo->query("
        SELECT
            COUNT(*) as total_registros,
            COUNT(DISTINCT especie) as total_especies,
            COUNT(DISTINCT cultivar) as total_cultivares,
            COUNT(DISTINCT uf) as total_estados,
            COUNT(DISTINCT municipio) as total_municipios,
            COALESCE(SUM(area), 0) as total_area,
            COALESCE(SUM(producao_estimada), 0) as total_producao
        FROM dados_campo
    ")->fetch();

    echo json_encode([
        'success' => true,
        'filters' => [
            'safras'     => $safras,
            'especies'   => $especies,
            'categorias' => $categorias,
            'estados'    => $estados,
            'statuses'   => $statuses,
            'cultivares' => $cultivares,
        ],
        'stats' => $stats,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
