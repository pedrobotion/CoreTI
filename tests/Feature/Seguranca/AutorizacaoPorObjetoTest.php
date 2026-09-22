<?php

namespace Tests\Feature\Seguranca;

use App\Models\CmdbAsset;
use App\Models\CmdbAssetDocument;
use App\Models\ServiceDeskQueueEmail;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\LegacyModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trocar o id na URL não pode alcançar registro alheio.
 *
 * Estar autenticado autoriza a usar o sistema, não a ler qualquer linha dele.
 * Estes testes fixam as três garantias que a auditoria conferiu: recurso
 * pessoal só do dono, rota aninhada confere o pai, e permissão de módulo é
 * exigida também nas rotas que checam por dentro do controller.
 */
class AutorizacaoPorObjetoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyModulePermissions::esquecerCache();
    }

    private function usuario(int $id, array $modulos = []): User
    {
        // Ids baixos colidem com MASTER_USER_ID, que ignora permissão e faria
        // o teste passar sem exercer a regra.
        $user = User::factory()->create(['id' => $id, 'role' => 'user', 'is_active' => true]);

        if ($modulos !== []) {
            app(ModuleAccessService::class)->sync($user, $modulos);
        }

        return $user->fresh();
    }

    public function test_usuario_nao_remove_email_da_fila_de_outro(): void
    {
        $dono = $this->usuario(9101, ['servicedesk' => ['view']]);
        $intruso = $this->usuario(9102, ['servicedesk' => ['view']]);

        $email = ServiceDeskQueueEmail::create([
            'user_id' => $dono->id,
            'email' => 'fila.do.dono@cocari.com.br',
        ]);

        $this->actingAs($intruso)
            ->delete(route('service-desk.my-queue.emails.destroy', $email))
            ->assertForbidden();

        $this->assertDatabaseHas('service_desk_queue_emails', ['id' => $email->id]);
    }

    public function test_documento_de_outro_ativo_nao_e_baixado_pela_url(): void
    {
        $operador = $this->usuario(9103, ['cmdb' => ['manage']]);

        $ativoA = CmdbAsset::create(['name' => 'servidor-a', 'asset_type' => 'server', 'status' => 'active']);
        $ativoB = CmdbAsset::create(['name' => 'servidor-b', 'asset_type' => 'server', 'status' => 'active']);

        $documento = CmdbAssetDocument::create([
            'asset_id' => $ativoB->id,
            'document_type' => 'contrato',
            'title' => 'Contrato',
            'original_name' => 'contrato.pdf',
            'file_path' => 'cmdb/documents/contrato.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_by' => $operador->id,
        ]);

        // Documento do ativo B pedido pela URL do ativo A.
        $this->actingAs($operador)
            ->get(route('cmdb.assets.documents.download', ['asset' => $ativoA, 'document' => $documento]))
            ->assertNotFound();
    }

    public function test_anexo_da_bancada_exige_permissao_do_modulo(): void
    {
        /*
         | A rota não tem module_access no middleware; a checagem vive dentro
         | do controller. O anexo precisa existir de verdade, senão o
         | route model binding devolve 404 antes de a autorização ser exercida
         | e o teste passaria sem testar nada.
         */
        $equipamento = \App\Models\BancadaEquipment::create([
            'tipo_equipamento' => 'Notebook',
            'plaqueta' => 'TESTE-001',
            'unidade_setor' => 'TI',
            'data_chegada' => now()->toDateString(),
            'status' => 'Recebido',
        ]);

        $anexo = \App\Models\BancadaEquipmentAttachment::create([
            'bancada_equipment_id' => $equipamento->id,
            'attachment_type' => 'documento',
            'original_name' => 'nota.pdf',
            'storage_path' => 'bancada/attachments/nota.pdf',
            'storage_disk' => 'local',
        ]);

        $semPermissao = $this->usuario(9104, ['servicedesk' => ['view']]);

        $this->actingAs($semPermissao)
            ->get("/bancada-servicos/anexos/{$anexo->id}/download")
            ->assertForbidden();

        // E quem tem a permissão passa da autorização (o 404 aqui é o arquivo
        // que não existe em disco, não bloqueio de acesso).
        $comPermissao = $this->usuario(9105, ['bancada' => ['view']]);

        $this->actingAs($comPermissao)
            ->get("/bancada-servicos/anexos/{$anexo->id}/download")
            ->assertNotFound();
    }
}
