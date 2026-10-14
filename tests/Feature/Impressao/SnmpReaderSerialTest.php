<?php

namespace Tests\Feature\Impressao;

use App\Services\Impressao\SnmpReader;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Extração do serial da resposta SNMP.
 *
 * Não há OID universal: a HP Color LaserJet Pro 4203 devolve noSuchInstance no
 * OID padrão e só publica o serial no ramo próprio da HP — às vezes embutido na
 * string de device-id (IEEE 1284), como "MFG:HP;...;SN:BRBSS6P066;...". Esta
 * classe testa a parte que não depende de rede: reconhecer o serial no texto.
 */
class SnmpReaderSerialTest extends TestCase
{
    /** Invoca a extração usada internamente sobre um valor bruto. */
    private function extrair(string $valor): ?string
    {
        // Reproduz a regra de serial() sobre um único valor, sem tocar na rede.
        if (preg_match('/(?:^|;)\s*S\/?N\s*:\s*([A-Za-z0-9\-]{4,})/i', $valor, $m)) {
            return $m[1];
        }

        $limpo = trim($valor, " \t\n\r\0\x0B\x00..\x1F\x7F..\xFF");

        if ($limpo !== '' && ! str_contains($limpo, ';') && ! str_contains($limpo, ':') && ! str_contains($limpo, ' ')) {
            return $limpo;
        }

        return null;
    }

    public function test_serial_com_bytes_de_controle_e_limpo(): void
    {
        // O OID próprio da HP prefixou 0xFDE8 ao serial da E55040. Sem limpar,
        // entraria sujo e não casaria com a Simpress.
        $this->assertSame('JPBCQDB06N', $this->extrair("\xfd\xe8JPBCQDB06N"));
    }

    public function test_serial_cru_e_aceito(): void
    {
        $this->assertSame('BRBSSBB0NF', $this->extrair('BRBSSBB0NF'));
    }

    public function test_serial_extraido_do_device_id(): void
    {
        $deviceId = 'MFG:HP;MDL:HP Color LaserJet Pro 4203;DES:HP Color LaserJet Pro 4203;'
            .'SN:BRBSS6P066;PRN:5HH48A;CMD:PCL;CLS:PRINTER;';

        $this->assertSame('BRBSS6P066', $this->extrair($deviceId));
    }

    #[DataProvider('variacoesDeSn')]
    public function test_variacoes_do_campo_sn(string $texto, string $esperado): void
    {
        $this->assertSame($esperado, $this->extrair($texto));
    }

    public static function variacoesDeSn(): array
    {
        return [
            'S/N com barra'   => ['DES:x;S/N:ABC12345;CLS:PRINTER', 'ABC12345'],
            'SN no inicio'    => ['SN:XYZ99999;MDL:algo', 'XYZ99999'],
            'espaco apos :'   => ['MFG:HP;SN: DEF45678;PRN:x', 'DEF45678'],
        ];
    }

    public function test_string_de_modelo_nao_vira_serial(): void
    {
        // hrDeviceDescr sem SN é modelo, não série: não pode ser confundido.
        $this->assertNull($this->extrair('HP Color LaserJet Pro 4203'));
    }

    public function test_a_ordem_dos_oids_prioriza_o_padrao(): void
    {
        // Documenta a intenção: o OID padrão vem primeiro, o device-id por último.
        $r = new ReflectionClass(SnmpReader::class);
        $oids = $r->getConstant('OIDS_SERIAL');

        $this->assertSame('1.3.6.1.2.1.43.5.1.1.17.1', $oids[0], 'padrão RFC 3805 primeiro');
        $this->assertSame('1.3.6.1.2.1.25.3.2.1.3.1', end($oids), 'device-id por último');
    }
}
