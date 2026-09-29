<?php

namespace App\Console\Commands;

use App\Models\AdOfficeRule;
use App\Models\AdUser;
use App\Models\CoretiCostCenter;
use App\Services\Ad\AdCadastroIncompletoService;
use App\Services\Ad\AdOfficeRuleService;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Planilha dos colaboradores com cadastro incompleto no AD.
 *
 * A tela de cadastro incompleto já exporta a lista em CSV. O que esta
 * planilha acrescenta é o cruzamento com a regra de OU: saber que faltam 82
 * centros de custo não diz o que fazer, mas saber que 9 deles estão em OUs
 * com regra pronta e 49 em OUs que ninguém confirmou, sim.
 *
 * Duas abas, porque as duas perguntas aparecem: quem não tem NENHUM dos
 * campos (a lista curta, por onde começar) e quem tem QUALQUER pendência.
 */
class RelatorioAdCadastro extends Command
{
    protected $signature = 'ad:relatorio-cadastro
        {--campos=matricula,Cargo,centro_custo : Campos cobrados, separados por vírgula}
        {--saida= : Caminho do arquivo (padrão: storage/app/private/reports)}';

    protected $description = 'Gera a planilha dos colaboradores com cadastro incompleto, com a situação da regra de cada OU.';

    private const CABECALHO = 'FF0F172A';

    public function handle(AdCadastroIncompletoService $cadastro, AdOfficeRuleService $regras): int
    {
        $campos = $this->campos();

        if ($campos === []) {
            $this->error('Nenhum campo válido em --campos. Use: '.implode(', ', array_keys(AdCadastroIncompletoService::CAMPOS)));

            return self::FAILURE;
        }

        $mapa = AdOfficeRule::all()->keyBy('ou_caminho');
        $nomes = CoretiCostCenter::active()->pluck('name', 'code');

        $linhas = $cadastro->consulta($campos)
            ->orderBy('nome_completo')
            ->get()
            ->map(function (AdUser $conta) use ($regras, $mapa, $nomes, $cadastro, $campos): array {
                $ou = $regras->caminhoDaOu($conta->distinguished_name);
                $regra = $ou === null ? null : $mapa->get($ou);

                [$situacao, $sugestao] = match (true) {
                    $regra === null => ['OU sem regra definida', ''],
                    (bool) $regra->ignorar => ['OU marcada para ignorar', ''],
                    $regra->aplicavel() => ['Regra pronta para aplicar', (string) $regra->valorParaAd()],
                    default => ['Regra a confirmar', (string) ($regra->sugestao_code ?? '')],
                };

                $faltam = array_values(array_filter(
                    $campos,
                    fn (string $campo): bool => $cadastro->faltaCampo($conta, $campo)
                ));

                return [
                    (string) $conta->nome_usuario,
                    (string) $conta->nome_completo,
                    (string) $conta->unidade_principal,
                    (string) $conta->unidade_setor,
                    (string) $conta->Cargo,
                    (string) $conta->matricula,
                    $ou ?? '(sem OU)',
                    $situacao,
                    $sugestao,
                    $sugestao !== '' ? (string) ($nomes[$sugestao] ?? '') : '',
                    implode(', ', array_map(fn (string $c): string => AdCadastroIncompletoService::CAMPOS[$c], $faltam)),
                    count($faltam) === count($campos) ? 'Sim' : 'Não',
                ];
            })
            ->all();

        if ($linhas === []) {
            $this->info('Nenhum colaborador com pendência nesses campos. Nada a gerar.');

            return self::SUCCESS;
        }

        // Quem não tem NENHUM dos campos pedidos: a lista por onde começar.
        $semNada = array_values(array_filter($linhas, fn (array $l): bool => $l[11] === 'Sim'));

        $planilha = new Spreadsheet();
        $this->abaDetalhe($planilha, $semNada, count($campos) === 1
            ? 'Sem '.AdCadastroIncompletoService::CAMPOS[$campos[0]]
            : 'Sem nenhum dos campos');
        $this->abaDetalhe($planilha, $linhas, 'Qualquer pendência', nova: true);
        $resumo = $this->abaResumo($planilha, $linhas);

        $destino = $this->option('saida')
            ?: storage_path('app/private/reports/ad_cadastro_incompleto_'.now()->local()->format('Ymd_His').'.xlsx');

        if (! is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0775, true);
        }

        (new Xlsx($planilha))->save($destino);

        $this->info('Planilha gerada: '.$destino);
        $this->newLine();
        $this->line(sprintf(
            '<info>%d contas com alguma pendência; destas, %d não têm nenhum dos campos (%s).</info>',
            count($linhas),
            count($semNada),
            implode(', ', array_map(fn (string $c): string => AdCadastroIncompletoService::CAMPOS[$c], $campos))
        ));
        $this->newLine();
        $this->table(
            ['Situação da regra', 'Contas', 'OUs'],
            collect($resumo)
                ->groupBy('situacao')
                ->map(fn ($grupo, $situacao): array => [
                    $situacao,
                    collect($grupo)->sum('contas'),
                    count($grupo),
                ])
                ->sortByDesc(fn (array $l): int => $l[1])
                ->values()
                ->all()
        );
        $this->line(sprintf('<info>%d contas em %d OUs.</info>', count($linhas), count($resumo)));

        return self::SUCCESS;
    }

    /**
     * Campos cobrados, na ordem em que o serviço os define.
     *
     * @return array<int, string>
     */
    private function campos(): array
    {
        $pedidos = array_filter(array_map('trim', explode(',', (string) $this->option('campos'))));

        return array_values(array_filter(
            array_keys(AdCadastroIncompletoService::CAMPOS),
            fn (string $campo): bool => in_array($campo, $pedidos, true)
        ));
    }

    /** @param  array<int, array<int, string>>  $linhas */
    private function abaDetalhe(Spreadsheet $planilha, array $linhas, string $titulo, bool $nova = false): void
    {
        $aba = $nova ? $planilha->createSheet() : $planilha->getActiveSheet();
        $aba->setTitle($titulo);
        $aba->fromArray([
            'Login', 'Nome', 'Unidade', 'Setor', 'Cargo', 'Matrícula',
            'OU (lotação)', 'Situação', 'Centro de custo sugerido', 'Nome do centro de custo',
            'O que falta', 'Falta tudo',
        ], null, 'A1');
        $aba->fromArray($linhas, null, 'A2');

        if ($linhas === []) {
            $this->enfeita($aba, 'L', 0);

            return;
        }

        /*
         | Matrícula e código de centro de custo são TEXTO.
         |
         | Como número, "64.200" vira 64.2 e "01.150" vira 1.15 — a planilha
         | come o zero e o código deixa de casar com o cadastro. Reescreve a
         | partir do dado de origem: ler a célula devolveria o valor que o
         | fromArray() já converteu.
         */
        foreach (['F' => 5, 'I' => 8] as $coluna => $indice) {
            foreach ($linhas as $n => $linha) {
                if ($linha[$indice] !== '') {
                    $aba->setCellValueExplicit($coluna.($n + 2), $linha[$indice], DataType::TYPE_STRING);
                }
            }
        }

        $this->enfeita($aba, 'L', count($linhas));
        $aba->setAutoFilter('A1:L'.(count($linhas) + 1));
    }

    /**
     * @param  array<int, array<int, string>>  $linhas
     * @return array<int, array{ou:string, contas:int, situacao:string, sugestao:string, nome:string}>
     */
    private function abaResumo(Spreadsheet $planilha, array $linhas): array
    {
        $resumo = [];

        foreach ($linhas as $l) {
            $resumo[$l[6]] ??= ['ou' => $l[6], 'contas' => 0, 'situacao' => $l[7], 'sugestao' => $l[8], 'nome' => $l[9]];
            $resumo[$l[6]]['contas']++;
        }

        $resumo = array_values($resumo);
        usort($resumo, fn (array $a, array $b): int => $b['contas'] <=> $a['contas']);

        $aba = $planilha->createSheet();
        $aba->setTitle('Resumo por OU');
        $aba->fromArray(['OU (lotação)', 'Contas pendentes', 'Situação da regra', 'Centro de custo sugerido', 'Nome'], null, 'A1');
        $aba->fromArray(array_map(
            fn (array $r): array => [$r['ou'], $r['contas'], $r['situacao'], $r['sugestao'], $r['nome']],
            $resumo
        ), null, 'A2');

        foreach ($resumo as $n => $r) {
            if ($r['sugestao'] !== '') {
                $aba->setCellValueExplicit('D'.($n + 2), $r['sugestao'], DataType::TYPE_STRING);
            }
        }

        $this->enfeita($aba, 'E', count($resumo));

        return $resumo;
    }

    private function enfeita(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $aba, string $ultimaColuna, int $linhas): void
    {
        $aba->getStyle('A1:'.$ultimaColuna.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $aba->getStyle('A1:'.$ultimaColuna.'1')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CABECALHO);
        $aba->freezePane('A2');

        foreach (range('A', $ultimaColuna) as $coluna) {
            $aba->getColumnDimension($coluna)->setAutoSize(true);
        }
    }
}
