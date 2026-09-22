<?php

namespace Database\Seeders;

use App\Models\AdmissionAccessProfile;
use Illuminate\Database\Seeder;

/**
 * Ponto de partida do catálogo de perfis.
 *
 * Cria apenas o perfil padrão (aplicável a qualquer cargo) e um cumulativo de
 * uniforme. O catálogo real — os 10 a 15 cargos que respondem pela maioria das
 * admissões — deve ser preenchido pela tela de Perfis de acesso junto com as
 * áreas; codificá-lo aqui só transferiria o conhecimento tácito de lugar.
 */
class AdmissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $padrao = AdmissionAccessProfile::firstOrCreate(
            ['nome' => 'Padrão — todos os colaboradores'],
            [
                'descricao' => 'Itens que toda admissão gera, independentemente de cargo ou unidade.',
                'prioridade' => 0,
                'cumulativo' => false,
                'ativo' => true,
            ]
        );

        $this->sincronizarItens($padrao, [
            'email_google',
            'acesso_ad',
            'cadastro_intranet',
            'equipamento',
            'cracha',
            'integracao',
        ]);

        $uniforme = AdmissionAccessProfile::firstOrCreate(
            ['nome' => 'Uniforme e EPI'],
            [
                'descricao' => 'Somado a qualquer perfil. Depende do tamanho informado pelo RH.',
                'prioridade' => 10,
                'cumulativo' => true,
                'ativo' => true,
            ]
        );

        $this->sincronizarItens($uniforme, ['uniforme']);
    }

    private function sincronizarItens(AdmissionAccessProfile $profile, array $taskTypes): void
    {
        foreach ($taskTypes as $taskType) {
            if (config("admissao.task_types.{$taskType}") === null) {
                continue;
            }

            $profile->items()->firstOrCreate(
                ['task_type' => $taskType],
                ['obrigatorio' => true]
            );
        }
    }
}
