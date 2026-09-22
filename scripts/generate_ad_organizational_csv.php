<?php

require __DIR__ . '/../vendor/autoload.php';

$input = $argv[1] ?? (__DIR__ . '/../correcao_ad.xlsx');
$output = $argv[2] ?? (__DIR__ . '/../ad_atualizacao_organizacional.csv');
$sheet = PhpOffice\PhpSpreadsheet\IOFactory::load($input)->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);
$header = array_map(fn ($value) => mb_strtolower(trim((string) $value)), array_shift($rows));
$columns = array_flip($header);

$handle = fopen($output, 'wb');
fwrite($handle, "\xEF\xBB\xBF");
fputcsv($handle, ['Login', 'Nome', 'Cargo', 'CentroCustoEscritorio'], ',');

$count = 0;
foreach ($rows as $row) {
    $login = trim((string) ($row[$columns['login']] ?? ''));
    $name = trim((string) ($row[$columns['nome']] ?? ''));
    $cargo = trim((string) ($row[$columns['cargo']] ?? ''));
    $costCenter = trim((string) ($row[$columns['centro de custo (escritório)']] ?? ''));

    if ($login === '' || $cargo === '' || $costCenter === '') {
        continue;
    }

    fputcsv($handle, [$login, $name, $cargo, $costCenter], ',');
    $count++;
}

fclose($handle);
echo "Registros exportados: {$count}\nArquivo: {$output}\n";
