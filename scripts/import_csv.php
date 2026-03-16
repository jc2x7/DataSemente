<?php
/**
 * Script de importação do CSV para MySQL
 *
 * Uso via terminal (SSH na Locaweb):
 *   php import_csv.php /caminho/para/arquivo.csv
 *
 * Ou via navegador (para arquivos menores):
 *   Acesse: https://seusite.com.br/scripts/import_csv.php?file=../dados/arquivo.csv
 *
 * IMPORTANTE: Remova ou proteja este arquivo após a importação!
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config.php';

// Determina o arquivo CSV
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Uso: php import_csv.php <caminho_do_csv>\n";
        exit(1);
    }
    $csvFile = $argv[1];
} else {
    if (!isset($_GET['file'])) {
        echo "<p>Passe o parâmetro ?file=caminho/do/arquivo.csv</p>";
        exit(1);
    }
    $csvFile = $_GET['file'];
    echo "<pre>";
}

if (!file_exists($csvFile)) {
    echo "Arquivo não encontrado: $csvFile\n";
    exit(1);
}

$pdo = getConnection();

// Detecta o delimitador (vírgula ou ponto-e-vírgula)
$firstLine = fgets(fopen($csvFile, 'r'));
$delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

echo "Delimitador detectado: '$delimiter'\n";
echo "Iniciando importação...\n\n";

$handle = fopen($csvFile, 'r');
if ($handle === false) {
    echo "Erro ao abrir arquivo.\n";
    exit(1);
}

// Lê o cabeçalho
$header = fgetcsv($handle, 0, $delimiter);
$header = array_map(function ($col) {
    // Normaliza nomes das colunas: remove acentos, lowercase, underscores
    $col = trim($col);
    $col = mb_strtolower($col, 'UTF-8');
    $col = str_replace(' ', '_', $col);
    $col = preg_replace('/[^a-z0-9_]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $col));
    return $col;
}, $header);

echo "Colunas encontradas no CSV:\n";
echo implode(' | ', $header) . "\n\n";

// Mapeamento das colunas do CSV para as colunas da tabela
// Ajuste este mapeamento conforme as colunas do seu CSV
$columnMap = [
    'safra'          => ['safra', 'ano_safra', 'ano', 'season', 'crop_year'],
    'cultura'        => ['cultura', 'crop', 'tipo_cultura', 'especie'],
    'cultivar'       => ['cultivar', 'variedade', 'variety', 'nome_cultivar'],
    'estado'         => ['estado', 'uf', 'state', 'sigla_uf'],
    'municipio'      => ['municipio', 'cidade', 'city', 'nome_municipio'],
    'regiao'         => ['regiao', 'region', 'macrorregiao'],
    'area_plantada'  => ['area_plantada', 'area', 'hectares', 'area_ha'],
    'produtividade'  => ['produtividade', 'yield', 'prod_ha', 'kg_ha'],
    'producao'       => ['producao', 'production', 'producao_total', 'toneladas'],
    'data_plantio'   => ['data_plantio', 'plantio', 'planting_date', 'dt_plantio'],
    'data_colheita'  => ['data_colheita', 'colheita', 'harvest_date', 'dt_colheita'],
    'observacoes'    => ['observacoes', 'obs', 'notas', 'observacao', 'notes'],
];

// Resolve o mapeamento
$resolvedMap = [];
foreach ($columnMap as $dbCol => $csvOptions) {
    foreach ($csvOptions as $opt) {
        $idx = array_search($opt, $header);
        if ($idx !== false) {
            $resolvedMap[$dbCol] = $idx;
            echo "  $dbCol => coluna '$opt' (índice $idx)\n";
            break;
        }
    }
}

echo "\n";

if (empty($resolvedMap)) {
    echo "AVISO: Nenhuma coluna mapeada automaticamente.\n";
    echo "As colunas do seu CSV são: " . implode(', ', $header) . "\n";
    echo "Ajuste o array \$columnMap neste script conforme seu CSV.\n\n";
    echo "Importando todas as colunas na ordem em que aparecem...\n";

    // Fallback: importa as primeiras colunas na ordem da tabela
    $dbColumns = array_keys($columnMap);
    for ($i = 0; $i < min(count($header), count($dbColumns)); $i++) {
        $resolvedMap[$dbColumns[$i]] = $i;
    }
}

// Prepara o INSERT
$dbCols = array_keys($resolvedMap);
$placeholders = implode(',', array_fill(0, count($dbCols), '?'));
$colNames = implode(',', $dbCols);

$sql = "INSERT INTO dados_campo ($colNames) VALUES ($placeholders)";
$stmt = $pdo->prepare($sql);

$batchSize = 1000;
$totalRows = 0;
$errors = 0;

$pdo->beginTransaction();

while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $values = [];
    foreach ($resolvedMap as $dbCol => $csvIdx) {
        $val = isset($row[$csvIdx]) ? trim($row[$csvIdx]) : null;

        // Trata valores vazios
        if ($val === '' || $val === '-' || $val === 'N/A') {
            $val = null;
        }

        // Converte campos numéricos (troca vírgula por ponto)
        if (in_array($dbCol, ['area_plantada', 'produtividade', 'producao']) && $val !== null) {
            $val = str_replace('.', '', $val);  // Remove separador de milhar
            $val = str_replace(',', '.', $val); // Troca vírgula decimal por ponto
        }

        // Trata datas no formato BR (dd/mm/aaaa)
        if (in_array($dbCol, ['data_plantio', 'data_colheita']) && $val !== null) {
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $val, $m)) {
                $val = "$m[3]-$m[2]-$m[1]";
            }
        }

        $values[] = $val;
    }

    try {
        $stmt->execute($values);
        $totalRows++;
    } catch (PDOException $e) {
        $errors++;
        if ($errors <= 10) {
            echo "Erro na linha $totalRows: " . $e->getMessage() . "\n";
        }
    }

    // Commit em lotes para performance
    if ($totalRows % $batchSize === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
        echo "  Importadas $totalRows linhas...\n";
    }
}

$pdo->commit();
fclose($handle);

echo "\n========================================\n";
echo "Importação concluída!\n";
echo "Total de linhas importadas: $totalRows\n";
echo "Erros: $errors\n";
echo "========================================\n";
