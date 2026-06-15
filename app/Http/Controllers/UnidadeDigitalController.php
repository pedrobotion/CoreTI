<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class UnidadeDigitalController extends Controller
{
    public function index(): View
    {
        return view('panels.unidade-digital', [
            'links' => self::links(),
        ]);
    }

    public function status(): JsonResponse
    {
        $statuses = Cache::remember('unidade_digital.links.status', now()->addMinutes(2), function (): array {
            $items = [];

            foreach (self::links() as $link) {
                $statusCode = null;
                $online = false;

                try {
                    $response = Http::withOptions([
                        'verify' => false,
                        'allow_redirects' => true,
                    ])
                        ->connectTimeout(3)
                        ->timeout(6)
                        ->get($link['href']);

                    $statusCode = $response->status();
                    $online = $statusCode >= 200 && $statusCode < 400;
                } catch (\Throwable) {
                    $online = false;
                }

                $items[$link['id']] = [
                    'online' => $online,
                    'status_code' => $statusCode,
                ];
            }

            return $items;
        });

        return response()->json([
            'checked_at' => now()->toIso8601String(),
            'items' => $statuses,
        ]);
    }

    public static function links(): array
    {
        return [
            [
                'id' => 'base-conhecimento',
                'title' => 'Base de Conhecimento',
                'description' => 'Artigos e instruções da Unidade Digital.',
                'href' => 'https://unidadedigital.tawk.help/article/emitir-nota-fiscal',
            ],
            [
                'id' => 'plugchat',
                'title' => 'Plugchat',
                'description' => 'Plataforma de atendimento e conversas.',
                'href' => 'https://www.plugchat.com.br/chat/b4077b57-71eb-41d3-a2c0-1f429ea2b503/login',
            ],
            [
                'id' => 'portal-app-cadastro',
                'title' => 'Portal APP Cadastro',
                'description' => 'Cadastro e manutenção no portal do app produtor.',
                'href' => 'https://intranet.cocari.com.br/appprodutor/login.php',
            ],
            [
                'id' => 'video-nf-produtor',
                'title' => 'Vídeo NF Produtor',
                'description' => 'Conteúdo de apoio para emissão de NF produtor.',
                'href' => 'https://red.cocari.com.br/form/0c934d80-c765-42ae-8bfb-81609040a6cd',
            ],
            [
                'id' => 'sap5-server',
                'title' => 'SAP 5 Server',
                'description' => 'Acesso ao ambiente SAP 5 para operação.',
                'href' => 'http://sap5.cocari.com.br:9898/produtorweb/',
            ],
            [
                'id' => 'consulta-cad-pro',
                'title' => 'Consulta CAD PRO',
                'description' => 'Consulta de cadastro no portal SINTEGRA PR.',
                'href' => 'https://www.sintegra.gov.br',
            ],
            [
                'id' => 'cadastro-receita-pr',
                'title' => 'Cadastro Receita PR',
                'description' => 'Portal para criação de usuário Receita PR.',
                'href' => 'https://www.fazenda.pr.gov.br/Pagina/Torne-se-usuario-do-ReceitaPR',
            ],
            [
                'id' => 'login-receita-pr-certificado',
                'title' => 'Login Receita PR Certificado',
                'description' => 'Acesso com certificado digital no Receita PR.',
                'href' => 'https://receita.pr.gov.br/certificado',
            ],
        ];
    }
}
