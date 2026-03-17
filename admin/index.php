<?php
/**
 * DataSemente - Painel Admin: Upload de CSV
 * Processamento em lotes via AJAX para evitar timeout
 */

session_start();
set_time_limit(120);
ini_set('memory_limit', '512M');

$ADMIN_PASSWORD = '290212';
$UPLOAD_DIR = __DIR__ . '/uploads';

// Garante pasta de uploads
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

// --- API AJAX ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if (empty($_SESSION['admin_auth'])) {
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }

    require_once __DIR__ . '/../config.php';

    switch ($_GET['action']) {

        // Etapa 1: Upload do arquivo
        case 'upload':
            handleUpload($UPLOAD_DIR);
            break;

        // Etapa 2: Limpar tabela
        case 'truncate':
            handleTruncate();
            break;

        // Etapa 3: Processar lote
        case 'process':
            handleProcess($UPLOAD_DIR);
            break;

        // Etapa 4: Finalizar
        case 'finish':
            handleFinish($UPLOAD_DIR);
            break;

        // Contagem
        case 'count':
            try {
                $pdo = getConnection();
                $count = $pdo->query('SELECT COUNT(*) FROM dados_campo')->fetchColumn();
                echo json_encode(['count' => (int)$count]);
            } catch (Exception $e) {
                echo json_encode(['count' => 0]);
            }
            break;

        default:
            echo json_encode(['error' => 'Ação inválida']);
    }
    exit;
}

// --- Logout ---
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// --- Login ---
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha'])) {
    if ($_POST['senha'] === $ADMIN_PASSWORD) {
        $_SESSION['admin_auth'] = true;
    } else {
        $loginError = 'Senha incorreta.';
    }
}

if (empty($_SESSION['admin_auth'])) {
    showLogin($loginError);
    exit;
}

// Contagem de registros
$totalRegistros = 0;
try {
    require_once __DIR__ . '/../config.php';
    $pdo = getConnection();
    $totalRegistros = $pdo->query('SELECT COUNT(*) FROM dados_campo')->fetchColumn();
} catch (Exception $e) {
    // banco ainda não configurado
}

showDashboard($totalRegistros);

// ===================== FUNÇÕES API =====================

function handleUpload(string $uploadDir): void
{
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $code = isset($_FILES['csv']) ? $_FILES['csv']['error'] : 'no file';
        echo json_encode(['error' => 'Erro no upload: código ' . $code]);
        return;
    }

    $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        echo json_encode(['error' => 'Apenas arquivos .csv são aceitos.']);
        return;
    }

    // Salva com nome fixo
    $dest = $uploadDir . '/import.csv';
    if (file_exists($dest)) {
        unlink($dest);
    }

    move_uploaded_file($_FILES['csv']['tmp_name'], $dest);

    // Conta linhas e detecta cabeçalho
    $handle = fopen($dest, 'r');
    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

    $header = fgetcsv($handle, 0, $delimiter);
    $totalLines = 0;
    while (fgets($handle) !== false) {
        $totalLines++;
    }
    fclose($handle);

    // Normaliza cabeçalho
    $header = array_map(function ($col) {
        $col = trim($col);
        $col = mb_strtolower($col, 'UTF-8');
        $col = str_replace(' ', '_', $col);
        $col = preg_replace('/[^a-z0-9_]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $col));
        return $col;
    }, $header);

    // Resolve mapeamento
    $columnMap = getColumnMap();
    $resolvedMap = [];
    foreach ($columnMap as $dbCol => $csvOptions) {
        foreach ($csvOptions as $opt) {
            $idx = array_search($opt, $header);
            if ($idx !== false) {
                $resolvedMap[$dbCol] = $idx;
                break;
            }
        }
    }

    if (empty($resolvedMap)) {
        echo json_encode(['error' => 'Nenhuma coluna reconhecida. Colunas encontradas: ' . implode(', ', $header)]);
        return;
    }

    // Salva metadados na sessão
    $_SESSION['import_meta'] = [
        'delimiter'   => $delimiter,
        'resolvedMap' => $resolvedMap,
        'totalLines'  => $totalLines,
    ];

    echo json_encode([
        'success'    => true,
        'totalLines' => $totalLines,
        'columns'    => array_keys($resolvedMap),
    ]);
}

function handleTruncate(): void
{
    try {
        $pdo = getConnection();
        $pdo->exec('DELETE FROM dados_campo');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro ao limpar tabela: ' . $e->getMessage()]);
    }
}

function handleProcess(string $uploadDir): void
{
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $batchSize = isset($_POST['batch']) ? (int)$_POST['batch'] : 5000;

    if (empty($_SESSION['import_meta'])) {
        echo json_encode(['error' => 'Metadados não encontrados. Faça upload novamente.']);
        return;
    }

    $meta = $_SESSION['import_meta'];
    $filePath = $uploadDir . '/import.csv';

    if (!file_exists($filePath)) {
        echo json_encode(['error' => 'Arquivo CSV não encontrado.']);
        return;
    }

    try {
        $pdo = getConnection();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro de conexão: ' . $e->getMessage()]);
        return;
    }

    $handle = fopen($filePath, 'r');
    $delimiter = $meta['delimiter'];
    $resolvedMap = $meta['resolvedMap'];

    // Pula cabeçalho
    fgetcsv($handle, 0, $delimiter);

    // Pula até o offset
    $skipped = 0;
    while ($skipped < $offset && fgetcsv($handle, 0, $delimiter) !== false) {
        $skipped++;
    }

    // Prepara INSERT em lote
    $dbCols = array_keys($resolvedMap);
    $colNames = implode(',', $dbCols);
    $singlePlaceholder = '(' . implode(',', array_fill(0, count($dbCols), '?')) . ')';

    $rows = [];
    $allValues = [];
    $read = 0;
    $errors = 0;

    while ($read < $batchSize && ($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $values = [];
        foreach ($resolvedMap as $dbCol => $csvIdx) {
            $val = isset($row[$csvIdx]) ? trim($row[$csvIdx]) : null;

            if ($val === '' || $val === '-' || $val === 'N/A') {
                $val = null;
            }

            if (in_array($dbCol, ['area', 'producao_bruta', 'producao_estimada']) && $val !== null) {
                $val = str_replace('.', '', $val);
                $val = str_replace(',', '.', $val);
            }

            if (in_array($dbCol, ['data_plantio', 'data_colheita']) && $val !== null) {
                if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $val, $m)) {
                    $val = "$m[3]-$m[2]-$m[1]";
                }
            }

            $values[] = $val;
        }

        $rows[] = $singlePlaceholder;
        $allValues = array_merge($allValues, $values);
        $read++;
    }

    fclose($handle);

    $inserted = 0;
    if (!empty($rows)) {
        // Insere em sub-lotes de 500 para não estourar memória
        $subBatch = 500;
        $colCount = count($dbCols);
        for ($i = 0; $i < count($rows); $i += $subBatch) {
            $chunk = array_slice($rows, $i, $subBatch);
            $valChunk = array_slice($allValues, $i * $colCount, count($chunk) * $colCount);

            $sql = "INSERT INTO dados_campo ($colNames) VALUES " . implode(',', $chunk);
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($valChunk);
                $inserted += count($chunk);
            } catch (PDOException $e) {
                $errors += count($chunk);
            }
        }
    }

    echo json_encode([
        'success'  => true,
        'read'     => $read,
        'inserted' => $inserted,
        'errors'   => $errors,
        'nextOffset' => $offset + $read,
        'done'     => $read < $batchSize,
    ]);
}

function handleFinish(string $uploadDir): void
{
    $filePath = $uploadDir . '/import.csv';
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    unset($_SESSION['import_meta']);

    try {
        $pdo = getConnection();
        $count = $pdo->query('SELECT COUNT(*) FROM dados_campo')->fetchColumn();
        echo json_encode(['success' => true, 'totalRecords' => (int)$count]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'totalRecords' => 0]);
    }
}

function getColumnMap(): array
{
    return [
        'safra'             => ['safra', 'ano_safra', 'crop_year'],
        'especie'           => ['especie', 'especies', 'species', 'cultura', 'crop'],
        'categoria'         => ['categoria', 'category', 'cat'],
        'cultivar'          => ['cultivar', 'variedade', 'variety'],
        'municipio'         => ['municipio', 'cidade', 'city', 'nome_municipio'],
        'uf'                => ['uf', 'estado', 'state', 'sigla_uf'],
        'status_registro'   => ['status', 'status_registro', 'situacao'],
        'data_plantio'      => ['data_do_plantio', 'data_plantio', 'plantio', 'dt_plantio'],
        'data_colheita'     => ['data_de_colheita', 'data_colheita', 'colheita', 'dt_colheita'],
        'area'              => ['area', 'area_plantada', 'hectares', 'area_ha'],
        'producao_bruta'    => ['producao_bruta', 'prod_bruta', 'producao_real'],
        'producao_estimada' => ['producao_estimada', 'prod_estimada', 'estimativa'],
    ];
}

// ===================== VIEWS =====================

function showLogin(string $error): void
{
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataSemente - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0f0f1a; color: #e8e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: #1a1a2e; border: 1px solid #2d2d44; border-radius: 16px; padding: 40px; width: 100%; max-width: 380px; text-align: center; }
        .login-box h1 { font-size: 1.4rem; font-weight: 800; color: #a29bfe; margin-bottom: 6px; }
        .login-box p { font-size: 0.8rem; color: #5a5b75; margin-bottom: 28px; }
        .login-box input { width: 100%; padding: 12px 14px; border: 1px solid #2d2d44; border-radius: 10px; background: #0f0f1a; color: #e8e8f0; font-size: 0.95rem; font-family: inherit; text-align: center; letter-spacing: 4px; margin-bottom: 16px; }
        .login-box input:focus { outline: none; border-color: #6c5ce7; box-shadow: 0 0 0 3px rgba(108,92,231,0.2); }
        .login-box button { width: 100%; padding: 12px; background: #6c5ce7; color: white; border: none; border-radius: 10px; font-size: 0.9rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: background 0.15s; }
        .login-box button:hover { background: #5a4bd1; }
        .error { color: #e17055; font-size: 0.82rem; margin-bottom: 14px; }
    </style>
</head>
<body>
    <form class="login-box" method="POST">
        <h1>DataSemente</h1>
        <p>Painel Administrativo</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <input type="password" name="senha" placeholder="Senha" autofocus required>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
<?php
}

function showDashboard(int $total): void
{
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataSemente - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0f0f1a; color: #e8e8f0; min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 1.3rem; font-weight: 800; color: #a29bfe; }
        .header a { color: #5a5b75; font-size: 0.82rem; text-decoration: none; }
        .header a:hover { color: #e17055; }
        .stat-card { background: #1a1a2e; border: 1px solid #2d2d44; border-radius: 12px; padding: 20px; margin-bottom: 20px; text-align: center; border-top: 3px solid #6c5ce7; }
        .stat-value { font-size: 2rem; font-weight: 800; color: #e8e8f0; }
        .stat-label { font-size: 0.72rem; color: #5a5b75; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; font-weight: 600; }
        .upload-card { background: #1a1a2e; border: 1px solid #2d2d44; border-radius: 12px; padding: 28px; }
        .upload-card h2 { font-size: 1rem; font-weight: 700; margin-bottom: 18px; }
        .drop-zone { border: 2px dashed #2d2d44; border-radius: 10px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.2s; margin-bottom: 16px; position: relative; }
        .drop-zone:hover, .drop-zone.dragover { border-color: #6c5ce7; background: rgba(108,92,231,0.05); }
        .drop-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .drop-zone .icon { font-size: 2.2rem; margin-bottom: 8px; }
        .drop-zone .label { font-size: 0.88rem; color: #8b8ca7; }
        .drop-zone .sublabel { font-size: 0.75rem; color: #5a5b75; margin-top: 4px; }
        .file-name { font-size: 0.82rem; color: #a29bfe; margin-bottom: 14px; display: none; text-align: center; }
        .btn { display: block; width: 100%; padding: 14px; background: #6c5ce7; color: white; border: none; border-radius: 10px; font-size: 0.9rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.15s; }
        .btn:hover { background: #5a4bd1; }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .msg { padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; line-height: 1.6; margin-bottom: 20px; display: none; }
        .msg.success { background: rgba(0,184,148,0.12); border: 1px solid rgba(0,184,148,0.3); color: #00b894; }
        .msg.error { background: rgba(225,112,85,0.12); border: 1px solid rgba(225,112,85,0.3); color: #e17055; }
        .hint { font-size: 0.75rem; color: #5a5b75; margin-top: 14px; line-height: 1.6; }
        .progress-area { display: none; margin-bottom: 16px; }
        .progress-bar { height: 8px; background: #2d2d44; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
        .progress-bar .fill { height: 100%; width: 0%; background: linear-gradient(90deg, #6c5ce7, #a29bfe); border-radius: 4px; transition: width 0.3s; }
        .progress-text { font-size: 0.78rem; color: #8b8ca7; text-align: center; }
        .progress-text .pct { color: #a29bfe; font-weight: 700; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin - DataSemente</h1>
            <a href="?logout=1">Sair</a>
        </div>

        <div class="stat-card">
            <div class="stat-value" id="stat-value"><?= number_format($total, 0, ',', '.') ?></div>
            <div class="stat-label">Registros no banco</div>
        </div>

        <div class="msg" id="msg"></div>

        <div class="upload-card">
            <h2>Importar CSV</h2>
            <div class="drop-zone" id="drop-zone">
                <input type="file" accept=".csv" id="csv-input">
                <div class="icon">&#128196;</div>
                <div class="label">Clique ou arraste o arquivo CSV</div>
                <div class="sublabel">Tamanho máximo: 100MB</div>
            </div>
            <div class="file-name" id="file-name"></div>
            <div class="progress-area" id="progress-area">
                <div class="progress-bar"><div class="fill" id="progress-fill"></div></div>
                <div class="progress-text" id="progress-text">Preparando...</div>
            </div>
            <button class="btn" id="btn-submit" disabled>Enviar e Importar</button>
            <div class="hint">
                O sistema detecta automaticamente as colunas do CSV (separador <strong>;</strong> ou <strong>,</strong>).<br>
                Colunas aceitas: safra, especie, categoria, cultivar, municipio, uf, status, area, producao_bruta, producao_estimada, data_plantio, data_colheita.
            </div>
        </div>
    </div>

    <script>
    const dropZone = document.getElementById('drop-zone');
    const csvInput = document.getElementById('csv-input');
    const fileNameEl = document.getElementById('file-name');
    const btnSubmit = document.getElementById('btn-submit');
    const progressArea = document.getElementById('progress-area');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    const msgEl = document.getElementById('msg');
    const statValue = document.getElementById('stat-value');

    const BATCH_SIZE = 5000;

    csvInput.addEventListener('change', () => {
        if (csvInput.files.length) {
            const f = csvInput.files[0];
            fileNameEl.textContent = f.name + ' (' + (f.size / 1024 / 1024).toFixed(1) + ' MB)';
            fileNameEl.style.display = 'block';
            btnSubmit.disabled = false;
            hideMsg();
        }
    });

    ['dragover', 'dragenter'].forEach(evt => {
        dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(evt => {
        dropZone.addEventListener(evt, e => { e.preventDefault(); dropZone.classList.remove('dragover'); });
    });

    btnSubmit.addEventListener('click', startImport);

    async function startImport() {
        const file = csvInput.files[0];
        if (!file) return;

        btnSubmit.disabled = true;
        dropZone.style.display = 'none';
        fileNameEl.style.display = 'none';
        progressArea.style.display = 'block';
        hideMsg();

        try {
            // Etapa 1: Upload
            setProgress(0, 'Enviando arquivo...');
            const formData = new FormData();
            formData.append('csv', file);

            const uploadRes = await fetch('?action=upload', { method: 'POST', body: formData });
            const uploadData = await uploadRes.json();

            if (uploadData.error) throw new Error(uploadData.error);

            const totalLines = uploadData.totalLines;
            const columns = uploadData.columns.join(', ');

            // Etapa 2: Limpar dados anteriores
            setProgress(5, 'Limpando dados anteriores...');
            const truncRes = await fetch('?action=truncate', { method: 'POST' });
            const truncData = await truncRes.json();
            if (truncData.error) throw new Error(truncData.error);

            // Etapa 3: Processar em lotes
            let offset = 0;
            let totalInserted = 0;
            let totalErrors = 0;

            while (true) {
                const pct = Math.round(10 + (offset / totalLines) * 85);
                setProgress(pct, `Importando... ${formatNum(offset)} / ${formatNum(totalLines)} linhas`);

                const batchForm = new FormData();
                batchForm.append('offset', offset);
                batchForm.append('batch', BATCH_SIZE);

                const batchRes = await fetch('?action=process', { method: 'POST', body: batchForm });
                const batchData = await batchRes.json();

                if (batchData.error) throw new Error(batchData.error);

                totalInserted += batchData.inserted;
                totalErrors += batchData.errors;
                offset = batchData.nextOffset;

                if (batchData.done) break;
            }

            // Etapa 4: Finalizar
            setProgress(98, 'Finalizando...');
            const finishRes = await fetch('?action=finish', { method: 'POST' });
            const finishData = await finishRes.json();

            setProgress(100, 'Concluído!');

            // Atualiza contador
            statValue.textContent = formatNum(finishData.totalRecords || totalInserted);

            let msg = `Importação concluída! <strong>${formatNum(totalInserted)}</strong> linhas importadas.`;
            if (totalErrors > 0) msg += `<br>${formatNum(totalErrors)} erros encontrados.`;
            msg += `<br>Colunas mapeadas: ${columns}`;
            showMsg('success', msg);

        } catch (err) {
            showMsg('error', err.message);
        }

        // Reset UI
        progressArea.style.display = 'none';
        dropZone.style.display = '';
        btnSubmit.disabled = false;
        csvInput.value = '';
        fileNameEl.style.display = 'none';
    }

    function setProgress(pct, text) {
        progressFill.style.width = pct + '%';
        progressText.innerHTML = text + ' <span class="pct">' + pct + '%</span>';
    }

    function showMsg(type, html) {
        msgEl.className = 'msg ' + type;
        msgEl.innerHTML = html;
        msgEl.style.display = 'block';
    }

    function hideMsg() {
        msgEl.style.display = 'none';
    }

    function formatNum(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    </script>
</body>
</html>
<?php
}
