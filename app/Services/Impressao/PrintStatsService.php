<?php

namespace App\Services\Impressao;

use App\Models\PrintJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregações do log de impressão.
 *
 * Tudo sai de print_jobs na hora. Não há tabela de resumo de propósito: o
 * período é escolhido na tela, e resumo pré-calculado ou engessa o recorte ou
 * desatualiza em silêncio.
 */
class PrintStatsService
{
    /** Quantos dias a tela mostra quando ninguém escolhe. */
    public const DIAS_PADRAO = 7;

    public function __construct(
        private readonly Carbon $de,
        private readonly Carbon $ate,
    ) {}

    public static function periodo(?string $de, ?string $ate): self
    {
        $fim = $ate ? Carbon::parse($ate)->endOfDay() : Carbon::now();
        $inicio = $de
            ? Carbon::parse($de)->startOfDay()
            : (clone $fim)->subDays(self::DIAS_PADRAO)->startOfDay();

        // Invertido é engano de digitação, não intenção: troca em vez de
        // devolver tela vazia sem explicação.
        return $inicio->greaterThan($fim) ? new self($fim, $inicio) : new self($inicio, $fim);
    }

    public function de(): Carbon
    {
        return $this->de;
    }

    public function ate(): Carbon
    {
        return $this->ate;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<PrintJob> */
    private function base()
    {
        return PrintJob::query()->noPeriodo($this->de, $this->ate);
    }

    /** @return array<string, mixed> */
    public function resumo(): array
    {
        $linha = $this->base()
            ->selectRaw('COUNT(*) trabalhos, COALESCE(SUM(paginas),0) paginas')
            ->selectRaw('COUNT(DISTINCT usuario) usuarios, COUNT(DISTINCT impressora) impressoras')
            ->selectRaw('COALESCE(MAX(paginas),0) maior, SUM(paginas = 0) sem_pagina')
            ->first();

        $trabalhos = (int) ($linha->trabalhos ?? 0);
        $paginas = (int) ($linha->paginas ?? 0);

        return [
            'trabalhos' => $trabalhos,
            'paginas' => $paginas,
            'usuarios' => (int) ($linha->usuarios ?? 0),
            'impressoras' => (int) ($linha->impressoras ?? 0),
            'maior_trabalho' => (int) ($linha->maior ?? 0),
            'sem_pagina' => (int) ($linha->sem_pagina ?? 0),
            'media_paginas' => $trabalhos > 0 ? round($paginas / $trabalhos, 2) : 0.0,
        ];
    }

    /**
     * Quem imprimiu, com a lotação vinda do Active Directory.
     *
     * "ana.amaro imprimiu 800 páginas" diz pouco; "ana.amaro, UBS - Faxinal"
     * já permite cobrar o setor certo. O vínculo é pelo login, que é o mesmo
     * dos dois lados — hoje 100% dos usuários que imprimem existem no AD.
     *
     * Sem JOIN no banco de propósito: ad_users é alimentada por outra
     * sincronização e pode estar vazia ou defasada. Uma junção faria o painel
     * de impressão sumir junto; buscar à parte só deixa a coluna vazia.
     *
     * @return Collection<int, object>
     */
    public function porUsuario(int $limite = 15): Collection
    {
        $linhas = $this->base()
            ->selectRaw('usuario, COUNT(*) trabalhos, COALESCE(SUM(paginas),0) paginas')
            ->selectRaw('MIN(impresso_em) primeira, MAX(impresso_em) ultima')
            ->groupBy('usuario')
            ->orderByDesc('paginas')
            ->limit($limite)
            ->get();

        return $this->comDadosDoAd($linhas);
    }

    /**
     * Acrescenta nome, lotação e cargo do AD a linhas que tenham 'usuario'.
     *
     * Cada campo vem separado e pode faltar sozinho: hoje 100% dos usuários
     * que imprimem têm lotação, mas 39 não têm cargo — e são quase os mesmos
     * que o relatório de cadastro incompleto aponta. Um campo vazio aqui é
     * pendência de cadastro lá, não falha da coleta.
     *
     * @param  Collection<int, object>  $linhas
     * @return Collection<int, object>
     */
    private function comDadosDoAd(Collection $linhas): Collection
    {
        if ($linhas->isEmpty() || ! \Illuminate\Support\Facades\Schema::hasTable('ad_users')) {
            return $linhas;
        }

        $logins = $linhas->pluck('usuario')->map(fn ($u): string => mb_strtolower(trim((string) $u)));

        $contas = DB::table('ad_users')
            ->whereIn(DB::raw('LOWER(nome_usuario)'), $logins)
            ->get(['nome_usuario', 'nome_completo', 'unidade_setor', 'Cargo'])
            ->keyBy(fn (object $c): string => mb_strtolower(trim((string) $c->nome_usuario)));

        return $linhas->map(function (object $linha) use ($contas): object {
            $conta = $contas->get(mb_strtolower(trim((string) $linha->usuario)));

            $linha->nome_completo = $this->ouNulo($conta->nome_completo ?? null);
            $linha->lotacao = $this->ouNulo($conta->unidade_setor ?? null);
            $linha->cargo = $this->ouNulo($conta->Cargo ?? null);

            return $linha;
        });
    }

    /** Texto em branco do AD vale como ausência, não como valor. */
    private function ouNulo(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : null;
    }

    /** @return Collection<int, object> */
    public function porImpressora(int $limite = 15): Collection
    {
        $total = max((int) $this->base()->sum('paginas'), 1);

        return $this->base()
            ->selectRaw('impressora, porta, printer_id, COUNT(*) trabalhos')
            ->selectRaw('COALESCE(SUM(paginas),0) paginas, COUNT(DISTINCT usuario) usuarios')
            ->groupBy('impressora', 'porta', 'printer_id')
            ->orderByDesc('paginas')
            ->limit($limite)
            ->get()
            ->map(function (object $linha) use ($total): object {
                $linha->percentual = round((int) $linha->paginas / $total * 100, 1);

                return $linha;
            });
    }

    /**
     * Volume por dia, sem buraco.
     *
     * Dia sem impressão não aparece no GROUP BY, e o gráfico emendaria uma
     * segunda na sexta anterior como se nada tivesse acontecido no meio.
     *
     * @return Collection<int, object>
     */
    public function porDia(): Collection
    {
        $vistos = $this->base()
            ->selectRaw('DATE(impresso_em) dia, COUNT(*) trabalhos, COALESCE(SUM(paginas),0) paginas')
            ->groupBy('dia')
            ->get()
            ->keyBy('dia');

        $dias = collect();

        for ($d = $this->de->copy()->startOfDay(); $d->lessThanOrEqualTo($this->ate); $d->addDay()) {
            $chave = $d->toDateString();
            $linha = $vistos->get($chave);

            $dias->push((object) [
                'dia' => $d->copy(),
                'trabalhos' => (int) ($linha->trabalhos ?? 0),
                'paginas' => (int) ($linha->paginas ?? 0),
            ]);
        }

        return $dias;
    }

    /**
     * Volume por hora do dia, nas 24 horas.
     *
     * A hora vem convertida para o fuso de exibição: os dados estão em UTC e
     * um pico das 14h apareceria às 17h.
     *
     * @return Collection<int, object>
     */
    public function porHorario(): Collection
    {
        /*
         | Deslocamento calculado aqui, não CONVERT_TZ.
         |
         | CONVERT_TZ depende das tabelas de fuso do MySQL, que neste servidor
         | NÃO estão carregadas: a função devolve NULL e todas as horas
         | cairiam num grupo só, deixando o gráfico zerado sem erro nenhum.
         |
         | O Brasil não tem mais horário de verão desde 2019, então um
         | deslocamento único vale para todo o período. Se voltar a ter, este
         | é o ponto a revisitar.
         */
        $segundos = Carbon::now(config('app.display_timezone', 'America/Sao_Paulo'))->utcOffset() * 60;

        $vistos = $this->base()
            ->selectRaw('HOUR(DATE_ADD(impresso_em, INTERVAL ? SECOND)) hora, COUNT(*) trabalhos, COALESCE(SUM(paginas),0) paginas', [$segundos])
            ->groupBy('hora')
            ->get()
            ->keyBy('hora');

        return collect(range(0, 23))->map(fn (int $h): object => (object) [
            'hora' => $h,
            'trabalhos' => (int) ($vistos[$h]->trabalhos ?? 0),
            'paginas' => (int) ($vistos[$h]->paginas ?? 0),
        ]);
    }

    /**
     * Faixas de peso por página.
     *
     * O log não guarda o nome do documento — o driver universal descarta o
     * título, e trocá-lo em 84 impressoras seria caro demais por um campo só.
     * O que sobra para distinguir O QUE foi impresso é o tamanho do spool
     * dividido pelas páginas: texto simples ocupa dezenas de KB, material
     * gráfico e digitalização passam de um MB por página.
     *
     * Não é o nome do arquivo, mas responde à pergunta prática: onde está
     * saindo papel caro.
     *
     * @return Collection<int, object>
     */
    public function porPerfil(): Collection
    {
        $faixas = [
            'texto' => ['rotulo' => 'Texto simples', 'ate' => 100 * 1024, 'cor' => '#059669'],
            'misto' => ['rotulo' => 'Misto', 'ate' => 1024 * 1024, 'cor' => '#0ea5e9'],
            'pesado' => ['rotulo' => 'Imagem ou digitalização', 'ate' => null, 'cor' => '#e11d48'],
        ];

        $linhas = $this->base()
            ->whereNotNull('tamanho_bytes')
            ->where('paginas', '>', 0)
            ->selectRaw('
                CASE
                    WHEN tamanho_bytes / paginas < ? THEN "texto"
                    WHEN tamanho_bytes / paginas < ? THEN "misto"
                    ELSE "pesado"
                END faixa,
                COUNT(*) trabalhos,
                COALESCE(SUM(paginas), 0) paginas
            ', [$faixas['texto']['ate'], $faixas['misto']['ate']])
            ->groupBy('faixa')
            ->get()
            ->keyBy('faixa');

        $totalPaginas = max((int) $linhas->sum('paginas'), 1);

        // Todas as faixas sempre presentes: faixa ausente some do gráfico e
        // some também da leitura de quem compara dois períodos.
        return collect($faixas)->map(fn (array $f, string $chave): object => (object) [
            'chave' => $chave,
            'rotulo' => $f['rotulo'],
            'cor' => $f['cor'],
            'trabalhos' => (int) ($linhas[$chave]->trabalhos ?? 0),
            'paginas' => (int) ($linhas[$chave]->paginas ?? 0),
            'percentual' => round((int) ($linhas[$chave]->paginas ?? 0) / $totalPaginas * 100, 1),
        ])->values();
    }

    /**
     * Trabalhos com mais peso POR PÁGINA.
     *
     * Diferente dos maiores trabalhos, que ordenam por quantidade: uma página
     * de 24 MB não aparece lá e é justamente a que vale olhar — foto, arte de
     * marketing ou digitalização em resolução alta demais para o que serve.
     *
     * @return Collection<int, object>
     */
    public function maisPesadosPorPagina(int $limite = 10): Collection
    {
        $linhas = $this->base()
            ->whereNotNull('tamanho_bytes')
            ->where('paginas', '>', 0)
            ->selectRaw('*, ROUND(tamanho_bytes / paginas / 1024) kb_por_pagina')
            ->orderByDesc(DB::raw('tamanho_bytes / paginas'))
            ->limit($limite)
            ->get();

        return $this->comDadosDoAd($linhas);
    }

    /** @return Collection<int, object> */
    public function maioresTrabalhos(int $limite = 10): Collection
    {
        $linhas = $this->base()
            ->orderByDesc('paginas')->orderByDesc('impresso_em')->limit($limite)->get();

        return $this->comDadosDoAd($linhas);
    }

    /**
     * Páginas por lotação.
     *
     * É a leitura que o rateio pede: o custo de impressão pertence ao setor,
     * não à pessoa.
     *
     * @return Collection<int, object>
     */
    public function porLotacao(int $limite = 15): Collection
    {
        /*
         | Agrupado em PHP, e não por JOIN.
         |
         | As duas tabelas têm collation diferente — print_jobs nasceu
         | utf8mb4_unicode_ci (o padrão do Laravel) e ad_users é
         | utf8mb4_general_ci (o padrão do banco). Juntar as duas por texto
         | devolve "Illegal mix of collations" e derruba a consulta.
         |
         | Dá para forçar COLLATE na cláusula, mas aí o painel de impressão
         | passaria a depender do esquema de outra sincronização. São 661
         | usuários distintos: agrupar aqui custa nada e mantém as duas
         | tabelas independentes.
         */
        $porUsuario = $this->comDadosDoAd(
            $this->base()
                ->selectRaw('usuario, COUNT(*) trabalhos, COALESCE(SUM(paginas),0) paginas')
                ->groupBy('usuario')
                ->get()
        );

        return $porUsuario
            ->filter(fn (object $l): bool => filled($l->lotacao ?? null))
            ->groupBy('lotacao')
            ->map(fn (Collection $grupo, string $lotacao): object => (object) [
                'lotacao' => $lotacao,
                'trabalhos' => (int) $grupo->sum('trabalhos'),
                'paginas' => (int) $grupo->sum('paginas'),
                'pessoas' => $grupo->count(),
            ])
            ->sortByDesc('paginas')
            ->take($limite)
            ->values();
    }

    /**
     * O que cada servidor reportou, e quem deixou de reportar.
     *
     * Um servidor que para de enviar sumiria dos números sem ninguém notar a
     * falta — por isso a lista esperada vem do config, e não do que chegou.
     *
     * @return Collection<int, object>
     */
    public function porServidor(): Collection
    {
        $vistos = PrintJob::query()
            ->selectRaw('servidor, COUNT(*) trabalhos, MAX(impresso_em) ultimo')
            ->groupBy('servidor')
            ->get()
            ->keyBy('servidor');

        $esperados = collect(config('impressao.servidores', []))
            ->map(fn ($s): string => mb_strtoupper(trim((string) $s)));

        return $esperados->merge($vistos->keys())->unique()->sort()->values()
            ->map(fn (string $nome): object => (object) [
                'servidor' => $nome,
                'trabalhos' => (int) ($vistos[$nome]->trabalhos ?? 0),
                'ultimo' => isset($vistos[$nome]) ? Carbon::parse($vistos[$nome]->ultimo) : null,
                'esperado' => $esperados->contains($nome),
            ]);
    }
}
