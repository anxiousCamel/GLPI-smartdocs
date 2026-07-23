<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Gerador de dados fictícios para testar o SmartDocs ponta a ponta com
 * um cenário realista de varejo (frente de caixa + setores de estoque).
 *
 * Cria:
 *   - 20 "frentes de caixa" completas, cada uma com 8 ativos: CPU,
 *     monitor, impressora térmica, nobreak, teclado, leitor de código
 *     de barras, balança de checkout e gaveta de dinheiro.
 *   - 20 balanças avulsas de setor (5 em cada: Estoque Geral, Câmara
 *     Fria, Recebimento, Expedição).
 *   - 5 balanças de setor de tipos diferentes (3 de conferência em
 *     "Conferência", 2 de pallet em "Paletização").
 *
 * Junto com toda a estrutura de apoio necessária: fabricantes, tipos,
 * modelos, localizações (em árvore) e grupos GLPI — cada frente de
 * caixa e cada setor vira um Grupo, e cada ativo recebe fabricante,
 * modelo, localização, estado (status) e grupo reais, para exercitar
 * qualquer binding key dinâmica do BindingKeyResolver (eq.fabricante,
 * eq.modelo, eq.grupo, eq.localizacao, eq.estado etc.) sem precisar de
 * nenhuma alteração de código.
 *
 * É IDEMPOTENTE: pode ser executado várias vezes sem duplicar dados —
 * dropdowns (fabricante/tipo/modelo/localização/grupo) são
 * localizados por nome, e ativos são localizados por `serial` antes
 * de inserir.
 *
 * ---------------------------------------------------------------------
 * COMO RODAR
 * ---------------------------------------------------------------------
 *
 * 1) Dentro do container Docker do GLPI (ambiente padrão deste repo):
 *
 *      docker cp plugins/smartdocs/tools/seed-test-fixtures.php \
 *          <container_glpi>:/tmp/seed-test-fixtures.php
 *      docker exec <container_glpi> php /tmp/seed-test-fixtures.php
 *
 *    (No Windows/Git Bash, prefixe os dois comandos com
 *    MSYS_NO_PATHCONV=1 para o path não ser reescrito.)
 *
 * 2) Direto na máquina, se você tiver PHP CLI com mysqli e acesso ao
 *    mesmo MySQL/MariaDB do GLPI:
 *
 *      php plugins/smartdocs/tools/seed-test-fixtures.php
 *
 * Por padrão conecta em host "mariadb" (nome do serviço no
 * docker-compose deste projeto), banco "glpi", usuário "root". Se o
 * seu ambiente for diferente (outro computador, outro docker-compose,
 * MySQL local), sobrescreva via variáveis de ambiente antes de rodar:
 *
 *      SMARTDOCS_SEED_DB_HOST=localhost \
 *      SMARTDOCS_SEED_DB_USER=glpi \
 *      SMARTDOCS_SEED_DB_PASS=glpi \
 *      SMARTDOCS_SEED_DB_NAME=glpi \
 *      php plugins/smartdocs/tools/seed-test-fixtures.php
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

$dbHost = getenv('SMARTDOCS_SEED_DB_HOST') ?: 'mariadb';
$dbUser = getenv('SMARTDOCS_SEED_DB_USER') ?: 'root';
$dbPass = getenv('SMARTDOCS_SEED_DB_PASS') ?: 'glpiroot';
$dbName = getenv('SMARTDOCS_SEED_DB_NAME') ?: 'glpi';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Falha ao conectar em {$dbHost}/{$dbName} como {$dbUser}: {$mysqli->connect_error}\n");
    fwrite(STDERR, "Dica: sobrescreva host/usuário/senha/banco via as variáveis de ambiente\n");
    fwrite(STDERR, "SMARTDOCS_SEED_DB_HOST / _USER / _PASS / _NAME. Veja o cabeçalho deste arquivo.\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

/**
 * Executa uma query preparada (ou simples, se sem parâmetros).
 * Retorna o insert_id (int) para INSERTs, ou o mysqli_result para SELECTs.
 */
function q(mysqli $db, string $sql, array $params = []): mysqli_result|bool|int
{
    if (empty($params)) {
        $result = $db->query($sql);
        if ($result === false) {
            fwrite(STDERR, "Erro SQL: {$db->error}\nQuery: {$sql}\n");
            exit(1);
        }
        return $result;
    }

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        fwrite(STDERR, "Erro ao preparar: {$db->error}\nQuery: {$sql}\n");
        exit(1);
    }

    $types = '';
    foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        fwrite(STDERR, "Erro ao executar: {$stmt->error}\nQuery: {$sql}\n");
        exit(1);
    }
    $insertId = $stmt->insert_id;
    $stmt->close();
    return $insertId;
}

function insertGetId(mysqli $db, string $table, array $data): int
{
    $cols = array_keys($data);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ") VALUES ({$placeholders})";
    return (int) q($db, $sql, array_values($data));
}

/** Busca um registro por `serial`; retorna o id existente ou null se não achar. */
function findBySerial(mysqli $db, string $table, string $serial): ?int
{
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE serial = ? LIMIT 1");
    $stmt->bind_param('s', $serial);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['id'] : null;
}

function lookupOrCreate(mysqli $db, string $table, string $name, array $extra = []): int
{
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return (int) $row['id'];
    }
    $stmt->close();

    $data = array_merge(['name' => $name, 'date_creation' => date('Y-m-d H:i:s'), 'date_mod' => date('Y-m-d H:i:s')], $extra);
    return insertGetId($db, $table, $data);
}

$stats = ['created' => 0, 'skipped' => 0];

/**
 * Insere um ativo se `serial` ainda não existir; senão, pula (idempotente).
 *
 * O patrimônio (otherserial) é gerado aqui dentro — não pelo chamador —
 * para só consumir um número da sequência quando um insert de fato
 * acontece. Se `nextPatrimonio()` fosse chamado no array literal do
 * chamador, o PHP a avaliaria antes mesmo de saber se o ativo já
 * existe, queimando números da sequência em toda reexecução.
 */
function seedAsset(mysqli $db, string $table, string $serial, array $data): void
{
    global $stats;
    if (findBySerial($db, $table, $serial) !== null) {
        $stats['skipped']++;
        return;
    }
    insertGetId($db, $table, array_merge($data, ['serial' => $serial, 'otherserial' => nextPatrimonio()]));
    $stats['created']++;
}

echo "=== 1. Fabricantes ===\n";
$manufacturers = [];
foreach ([
    'Dell', 'HP', 'LG', 'AOC', 'Elgin', 'Bematech', 'SMS', 'APC',
    'Toledo do Brasil', 'Filizola', 'Urano', 'Gertec', 'Honeywell',
] as $name) {
    $manufacturers[$name] = lookupOrCreate($mysqli, 'glpi_manufacturers', $name);
}
echo count($manufacturers) . " fabricantes\n";

echo "=== 2. Estados (status) ===\n";
$states = [];
foreach (['Em uso', 'Em manutenção'] as $name) {
    $states[$name] = lookupOrCreate($mysqli, 'glpi_states', $name);
}

echo "=== 3. Tipos ===\n";
$computertypes = ['Desktop PDV' => lookupOrCreate($mysqli, 'glpi_computertypes', 'Desktop PDV')];
$monitortypes = ['Monitor LED' => lookupOrCreate($mysqli, 'glpi_monitortypes', 'Monitor LED')];
$printertypes = ['Impressora Térmica' => lookupOrCreate($mysqli, 'glpi_printertypes', 'Impressora Térmica')];
$peripheraltypes = [];
foreach ([
    'Nobreak', 'Teclado PDV', 'Leitor de Código de Barras', 'Balança de Checkout',
    'Gaveta de Dinheiro', 'Balança de Setor', 'Balança de Conferência', 'Balança de Pallet',
] as $name) {
    $peripheraltypes[$name] = lookupOrCreate($mysqli, 'glpi_peripheraltypes', $name);
}

echo "=== 4. Modelos ===\n";
function model(mysqli $db, string $table, string $name, string $productNumber): int
{
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return (int) $row['id'];
    }
    $stmt->close();
    return insertGetId($db, $table, [
        'name' => $name,
        'product_number' => $productNumber,
        'date_creation' => date('Y-m-d H:i:s'),
        'date_mod' => date('Y-m-d H:i:s'),
    ]);
}

$computermodels = [
    'Dell' => model($mysqli, 'glpi_computermodels', 'OptiPlex 3020 SFF', 'OPX3020'),
    'HP' => model($mysqli, 'glpi_computermodels', 'ProDesk 400 G6', 'PD400G6'),
];
$monitormodels = [
    'LG' => model($mysqli, 'glpi_monitormodels', '22MK430H', '22MK430H'),
    'AOC' => model($mysqli, 'glpi_monitormodels', '22B1H', '22B1H'),
];
$printermodels = [
    'Elgin' => model($mysqli, 'glpi_printermodels', 'Elgin i9', 'ELGI9'),
    'Bematech' => model($mysqli, 'glpi_printermodels', 'Bematech MP-4200 TH', 'MP4200TH'),
];
$peripheralmodels = [
    'Nobreak_SMS' => model($mysqli, 'glpi_peripheralmodels', 'SMS Net4+ 1200VA', 'NET4P1200'),
    'Nobreak_APC' => model($mysqli, 'glpi_peripheralmodels', 'APC Back-UPS 600VA', 'BE600'),
    'Teclado_Gertec' => model($mysqli, 'glpi_peripheralmodels', 'Gertec TCL-100', 'TCL100'),
    'Leitor_Honeywell' => model($mysqli, 'glpi_peripheralmodels', 'Honeywell Voyager 1200g', 'VG1200G'),
    'Leitor_Gertec' => model($mysqli, 'glpi_peripheralmodels', 'Gertec GBAR', 'GBAR'),
    'BalCheckout_Toledo' => model($mysqli, 'glpi_peripheralmodels', 'Toledo Prix 3 Fit', 'PRIX3FIT'),
    'BalCheckout_Filizola' => model($mysqli, 'glpi_peripheralmodels', 'Filizola CS-15', 'CS15'),
    'Gaveta_Elgin' => model($mysqli, 'glpi_peripheralmodels', 'Elgin GAV-01', 'GAV01'),
    'BalSetor_Toledo' => model($mysqli, 'glpi_peripheralmodels', 'Toledo 2098', 'TOL2098'),
    'BalSetor_Filizola' => model($mysqli, 'glpi_peripheralmodels', 'Filizola MF', 'MF'),
    'BalConf_Urano' => model($mysqli, 'glpi_peripheralmodels', 'Urano US 30/210', 'US30210'),
    'BalPallet_Urano' => model($mysqli, 'glpi_peripheralmodels', 'Urano UPB', 'UPB'),
];

echo "=== 5. Localizações ===\n";
function location(mysqli $db, string $name, int $parentId, string $completename, int $level): int
{
    $stmt = $db->prepare("SELECT id FROM glpi_locations WHERE name = ? AND locations_id = ? LIMIT 1");
    $stmt->bind_param('si', $name, $parentId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return (int) $row['id'];
    }
    $stmt->close();
    return insertGetId($db, 'glpi_locations', [
        'name' => $name,
        'locations_id' => $parentId,
        'completename' => $completename,
        'level' => $level,
        'date_creation' => date('Y-m-d H:i:s'),
        'date_mod' => date('Y-m-d H:i:s'),
    ]);
}

$locFrenteCaixa = location($mysqli, 'Frente de Caixa', 0, 'Frente de Caixa', 1);
$sectorLocations = [
    'Estoque Geral' => location($mysqli, 'Estoque Geral', 0, 'Estoque Geral', 1),
    'Câmara Fria' => location($mysqli, 'Câmara Fria', 0, 'Câmara Fria', 1),
    'Recebimento' => location($mysqli, 'Recebimento', 0, 'Recebimento', 1),
    'Expedição' => location($mysqli, 'Expedição', 0, 'Expedição', 1),
    'Conferência' => location($mysqli, 'Conferência', 0, 'Conferência', 1),
    'Paletização' => location($mysqli, 'Paletização', 0, 'Paletização', 1),
];

$checkoutLocations = [];
for ($i = 1; $i <= 20; $i++) {
    $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $name = "Checkout {$num}";
    $checkoutLocations[$i] = location($mysqli, $name, $locFrenteCaixa, "Frente de Caixa > {$name}", 2);
}
echo (1 + count($sectorLocations) + count($checkoutLocations)) . " localizações\n";

echo "=== 6. Grupos ===\n";
function groupGet(mysqli $db, string $name, int $parentId, string $completename): int
{
    $stmt = $db->prepare("SELECT id FROM glpi_groups WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return (int) $row['id'];
    }
    $stmt->close();
    return insertGetId($db, 'glpi_groups', [
        'name' => $name,
        'groups_id' => $parentId,
        'completename' => $completename,
        'level' => 1,
        'date_creation' => date('Y-m-d H:i:s'),
        'date_mod' => date('Y-m-d H:i:s'),
    ]);
}

$checkoutGroups = [];
for ($i = 1; $i <= 20; $i++) {
    $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $name = "PDV {$num}";
    $checkoutGroups[$i] = groupGet($mysqli, $name, 0, $name);
}
$sectorGroups = [];
foreach (array_keys($sectorLocations) as $sectorName) {
    $gname = "Setor - {$sectorName}";
    $sectorGroups[$sectorName] = groupGet($mysqli, $gname, 0, $gname);
}
echo (count($checkoutGroups) + count($sectorGroups)) . " grupos\n";

// ------------------------------------------------------------------
// 7. Patrimônio global sequencial (otherserial): continua a partir do
// maior PAT-###### já existente, para não colidir em reexecuções.
// ------------------------------------------------------------------
function nextPatrimonioSeed(mysqli $db): int
{
    $max = 0;
    foreach (['glpi_computers', 'glpi_monitors', 'glpi_printers', 'glpi_peripherals'] as $table) {
        $res = q($db, "SELECT otherserial FROM {$table} WHERE otherserial LIKE 'PAT-%'");
        while ($row = $res->fetch_assoc()) {
            $n = (int) substr($row['otherserial'], 4);
            $max = max($max, $n);
        }
    }
    return $max + 1;
}
$patrimonioSeq = nextPatrimonioSeed($mysqli);
function nextPatrimonio(): string
{
    global $patrimonioSeq;
    return 'PAT-' . str_pad((string) $patrimonioSeq++, 6, '0', STR_PAD_LEFT);
}

function baseAssetFields(int $entityId, int $locationId, int $manufacturerId, int $stateId, int $groupId): array
{
    return [
        'entities_id' => $entityId,
        'is_recursive' => 0,
        'locations_id' => $locationId,
        'manufacturers_id' => $manufacturerId,
        'states_id' => $stateId,
        'groups_id' => $groupId,
        'groups_id_tech' => 0,
        'users_id' => 0,
        'users_id_tech' => 0,
        'is_deleted' => 0,
        'is_template' => 0,
        'is_dynamic' => 0,
        'date_creation' => date('Y-m-d H:i:s'),
        'date_mod' => date('Y-m-d H:i:s'),
    ];
}

$entityId = 0;

echo "=== 8. 20 Frentes de Caixa completas ===\n";
for ($i = 1; $i <= 20; $i++) {
    $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $locId = $checkoutLocations[$i];
    $groupId = $checkoutGroups[$i];
    $stateId = ($i % 9 === 0) ? $states['Em manutenção'] : $states['Em uso'];

    seedAsset($mysqli, 'glpi_computers', "PDV-CPU-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $locId, $manufacturers['Dell'], $stateId, $groupId),
        [
            'name' => "PDV {$num} - CPU",
            'computertypes_id' => $computertypes['Desktop PDV'],
            'computermodels_id' => $computermodels['Dell'],
            'autoupdatesystems_id' => 0,
            'networks_id' => 0,
        ]
    ));

    seedAsset($mysqli, 'glpi_monitors', "PDV-MON-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $locId, $manufacturers['LG'], $stateId, $groupId),
        [
            'name' => "PDV {$num} - Monitor",
            'monitortypes_id' => $monitortypes['Monitor LED'],
            'monitormodels_id' => $monitormodels['LG'],
            'size' => 21.5,
            'is_global' => 0,
        ]
    ));

    seedAsset($mysqli, 'glpi_printers', "PDV-IMP-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $locId, $manufacturers['Elgin'], $stateId, $groupId),
        [
            'name' => "PDV {$num} - Impressora Térmica",
            'printertypes_id' => $printertypes['Impressora Térmica'],
            'printermodels_id' => $printermodels['Elgin'],
            'init_pages_counter' => 0,
            'last_pages_counter' => 0,
            'is_global' => 0,
        ]
    ));

    $nobreakManuf = ($i % 2 === 0) ? 'SMS' : 'APC';
    $nobreakModel = ($nobreakManuf === 'SMS') ? $peripheralmodels['Nobreak_SMS'] : $peripheralmodels['Nobreak_APC'];
    seedAsset($mysqli, 'glpi_peripherals', "PDV-NBK-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $locId, $manufacturers[$nobreakManuf], $stateId, $groupId),
        [
            'name' => "PDV {$num} - Nobreak",
            'peripheraltypes_id' => $peripheraltypes['Nobreak'],
            'peripheralmodels_id' => $nobreakModel,
            'is_global' => 0,
        ]
    ));

    seedAsset($mysqli, 'glpi_peripherals', "PDV-TEC-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $locId, $manufacturers['Gertec'], $stateId, $groupId),
        [
            'name' => "PDV {$num} - Teclado",
            'peripheraltypes_id' => $peripheraltypes['Teclado PDV'],
            'peripheralmodels_id' => $peripheralmodels['Teclado_Gertec'],
            'is_global' => 0,
        ]
    ));

    $leitorManuf = ($i % 2 === 0) ? 'Honeywell' : 'Gertec';
    $leitorModel = ($leitorManuf === 'Honeywell') ? $peripheralmodels['Leitor_Honeywell'] : $peripheralmodels['Leitor_Gertec'];
    seedAsset($mysqli, 'glpi_peripherals', "PDV-LEI-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $locId, $manufacturers[$leitorManuf], $stateId, $groupId),
        [
            'name' => "PDV {$num} - Leitor de Código de Barras",
            'peripheraltypes_id' => $peripheraltypes['Leitor de Código de Barras'],
            'peripheralmodels_id' => $leitorModel,
            'is_global' => 0,
        ]
    ));

    $balManuf = ($i % 2 === 0) ? 'Toledo do Brasil' : 'Filizola';
    $balModel = ($balManuf === 'Toledo do Brasil') ? $peripheralmodels['BalCheckout_Toledo'] : $peripheralmodels['BalCheckout_Filizola'];
    seedAsset($mysqli, 'glpi_peripherals', "PDV-BAL-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $locId, $manufacturers[$balManuf], $stateId, $groupId),
        [
            'name' => "PDV {$num} - Balança de Checkout",
            'peripheraltypes_id' => $peripheraltypes['Balança de Checkout'],
            'peripheralmodels_id' => $balModel,
            'is_global' => 0,
        ]
    ));

    seedAsset($mysqli, 'glpi_peripherals', "PDV-GAV-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $locId, $manufacturers['Elgin'], $stateId, $groupId),
        [
            'name' => "PDV {$num} - Gaveta de Dinheiro",
            'peripheraltypes_id' => $peripheraltypes['Gaveta de Dinheiro'],
            'peripheralmodels_id' => $peripheralmodels['Gaveta_Elgin'],
            'is_global' => 0,
        ]
    ));
}
echo "20 frentes de caixa x 8 itens = 160 ativos (verificados/criados)\n";

echo "=== 9. 20 Balanças avulsas de setor ===\n";
$sectorNames = ['Estoque Geral', 'Câmara Fria', 'Recebimento', 'Expedição'];
$sectorSeq = array_fill_keys($sectorNames, 0);
for ($i = 1; $i <= 20; $i++) {
    $sectorName = $sectorNames[($i - 1) % count($sectorNames)];
    $sectorSeq[$sectorName]++;
    $seqNum = str_pad((string) $sectorSeq[$sectorName], 2, '0', STR_PAD_LEFT);
    $locId = $sectorLocations[$sectorName];
    $groupId = $sectorGroups[$sectorName];
    $balManuf = ($i % 2 === 0) ? 'Toledo do Brasil' : 'Filizola';
    $balModel = ($balManuf === 'Toledo do Brasil') ? $peripheralmodels['BalSetor_Toledo'] : $peripheralmodels['BalSetor_Filizola'];

    seedAsset($mysqli, 'glpi_peripherals', "SET-BAL-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $locId, $manufacturers[$balManuf], $states['Em uso'], $groupId),
        [
            'name' => "Balança Setor - {$sectorName} {$seqNum}",
            'peripheraltypes_id' => $peripheraltypes['Balança de Setor'],
            'peripheralmodels_id' => $balModel,
            'is_global' => 0,
        ]
    ));
}
echo "20 balanças de setor (5 em cada: " . implode(', ', $sectorNames) . ")\n";

echo "=== 10. 5 Balanças de setor de tipos diferentes (conferência/pallet) ===\n";
for ($i = 1; $i <= 3; $i++) {
    $seqNum = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    seedAsset($mysqli, 'glpi_peripherals', "SET-BALCONF-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $sectorLocations['Conferência'], $manufacturers['Urano'], $states['Em uso'], $sectorGroups['Conferência']),
        [
            'name' => "Balança Conferência {$seqNum}",
            'peripheraltypes_id' => $peripheraltypes['Balança de Conferência'],
            'peripheralmodels_id' => $peripheralmodels['BalConf_Urano'],
            'is_global' => 0,
        ]
    ));
}
for ($i = 1; $i <= 2; $i++) {
    $seqNum = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    seedAsset($mysqli, 'glpi_peripherals', "SET-BALPAL-" . str_pad((string) $i, 4, '0', STR_PAD_LEFT), array_merge(
        baseAssetFields($entityId, $sectorLocations['Paletização'], $manufacturers['Urano'], $states['Em uso'], $sectorGroups['Paletização']),
        [
            'name' => "Balança Pallet {$seqNum}",
            'peripheraltypes_id' => $peripheraltypes['Balança de Pallet'],
            'peripheralmodels_id' => $peripheralmodels['BalPallet_Urano'],
            'is_global' => 0,
        ]
    ));
}
echo "3 balanças de conferência + 2 balanças de pallet\n";

echo "\n=== RESUMO FINAL ===\n";
echo "Ativos criados nesta execução: {$stats['created']}\n";
echo "Ativos já existentes (pulados): {$stats['skipped']}\n";
echo 'Próximo patrimônio livre: PAT-' . str_pad((string) $patrimonioSeq, 6, '0', STR_PAD_LEFT) . "\n";
