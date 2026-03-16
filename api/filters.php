<?php
/**
 * Retorna as opções disponíveis para os filtros (select dropdowns)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();

    $safras = $pdo->query("SELECT DISTINCT safra FROM dados_campo WHERE safra IS NOT NULL ORDER BY safra DESC")
                  ->fetchAll(PDO::FETCH_COLUMN);

    $culturas = $pdo->query("SELECT DISTINCT cultura FROM dados_campo WHERE cultura IS NOT NULL ORDER BY cultura")
                    ->fetchAll(PDO::FETCH_COLUMN);

    $estados = $pdo->query("SELECT DISTINCT estado FROM dados_campo WHERE estado IS NOT NULL ORDER BY estado")
                   ->fetchAll(PDO::FETCH_COLUMN);

    // Estatísticas gerais
    $stats = $pdo->query("
        SELECT
            COUNT(*) as total_registros,
            COUNT(DISTINCT cultura) as total_culturas,
            COUNT(DISTINCT estado) as total_estados,
            COUNT(DISTINCT municipio) as total_municipios
        FROM dados_campo
    ")->fetch();

    echo json_encode([
        'success' => true,
        'filters' => [
            'safras'   => $safras,
            'culturas' => $culturas,
            'estados'  => $estados,
        ],
        'stats' => $stats,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Erro ao carregar filtros: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
