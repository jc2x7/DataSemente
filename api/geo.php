<?php
/**
 * API de geolocalização - Dados agrupados por município com coordenadas
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

try {
    $pdo = getConnection();
    [$whereClause, $params] = buildFilters($_GET);

    $sql = "SELECT municipio, uf,
                   COUNT(*) as registros,
                   COALESCE(SUM(area), 0) as total_area,
                   COALESCE(SUM(producao_estimada), 0) as total_producao,
                   COALESCE(SUM(producao_bruta), 0) as total_producao_bruta,
                   COUNT(DISTINCT cultivar) as total_cultivares
            FROM dados_campo
            $whereClause
            GROUP BY municipio, uf
            ORDER BY total_area DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Carrega coordenadas
    $coordsFile = __DIR__ . '/../assets/data/municipios_coords.json';
    $coords = [];
    if (file_exists($coordsFile)) {
        $coords = json_decode(file_get_contents($coordsFile), true) ?: [];
    }

    // Coordenadas das capitais como fallback
    $capitais = [
        'AC' => [-9.97, -67.81], 'AL' => [-9.67, -35.74], 'AM' => [-3.12, -60.02],
        'AP' => [0.03, -51.05], 'BA' => [-12.97, -38.51], 'CE' => [-3.72, -38.53],
        'DF' => [-15.78, -47.93], 'ES' => [-20.32, -40.34], 'GO' => [-16.68, -49.25],
        'MA' => [-2.53, -44.28], 'MG' => [-19.92, -43.94], 'MS' => [-20.44, -54.65],
        'MT' => [-15.60, -56.10], 'PA' => [-1.46, -48.50], 'PB' => [-7.12, -34.86],
        'PE' => [-8.05, -34.87], 'PI' => [-5.09, -42.80], 'PR' => [-25.43, -49.27],
        'RJ' => [-22.91, -43.17], 'RN' => [-5.79, -35.21], 'RO' => [-8.76, -63.90],
        'RR' => [2.82, -60.67], 'RS' => [-30.03, -51.23], 'SC' => [-27.59, -48.55],
        'SE' => [-10.91, -37.07], 'SP' => [-23.55, -46.63], 'TO' => [-10.18, -48.33],
    ];

    $features = [];
    $totalMunicipios = 0;
    $totalRegistros = 0;

    foreach ($rows as $row) {
        if (empty($row['municipio']) || empty($row['uf'])) continue;

        $key = mb_strtoupper(trim($row['municipio']), 'UTF-8') . '-' . strtoupper(trim($row['uf']));
        $lat = null;
        $lng = null;

        if (isset($coords[$key])) {
            $lat = $coords[$key][0];
            $lng = $coords[$key][1];
        } else {
            // Tenta buscar sem acentos
            $keyNorm = strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($row['municipio']))) . '-' . strtoupper(trim($row['uf']));
            if (isset($coords[$keyNorm])) {
                $lat = $coords[$keyNorm][0];
                $lng = $coords[$keyNorm][1];
            } elseif (isset($capitais[strtoupper(trim($row['uf']))])) {
                // Fallback: capital do estado com pequeno offset aleatório
                $cap = $capitais[strtoupper(trim($row['uf']))];
                $lat = $cap[0] + (crc32($key) % 100) / 500;
                $lng = $cap[1] + (crc32($key . 'lng') % 100) / 500;
            }
        }

        if ($lat === null) continue;

        $totalMunicipios++;
        $totalRegistros += (int)$row['registros'];

        $features[] = [
            'municipio' => $row['municipio'],
            'uf'        => $row['uf'],
            'lat'       => (float)$lat,
            'lng'       => (float)$lng,
            'registros' => (int)$row['registros'],
            'area'      => (float)$row['total_area'],
            'producao'  => (float)$row['total_producao'],
            'cultivares'=> (int)$row['total_cultivares'],
        ];
    }

    echo json_encode([
        'success'         => true,
        'totalMunicipios' => $totalMunicipios,
        'totalRegistros'  => $totalRegistros,
        'data'            => $features,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
