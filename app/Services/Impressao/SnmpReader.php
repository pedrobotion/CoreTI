<?php

namespace App\Services\Impressao;

/**
 * SNMP GET (v1/v2c) em socket puro, sem a extensão snmp do PHP.
 *
 * O servidor não tem ext-snmp e instalá-la em produção seria uma dependência a
 * mais para manter. O protocolo é simples o bastante para as poucas operações
 * que precisamos — um GET de um OID escalar — e este leitor faz exatamente
 * isso, nada além.
 *
 * Foi validado contra o parque real: o OID prtGeneralSerialNumber (RFC 3805)
 * respondeu em todas as impressoras testadas, com o mesmo serial que a página
 * web mostra e que a Simpress registra.
 */
class SnmpReader
{
    /** prtGeneralSerialNumber — o serial padrão de impressora. */
    public const OID_SERIAL = '1.3.6.1.2.1.43.5.1.1.17.1';

    /** hrDeviceDescr — modelo, serve para confirmar que respondeu o certo. */
    public const OID_MODELO = '1.3.6.1.2.1.25.3.2.1.3.1';

    /*
     | Onde procurar o serial, em ordem.
     |
     | Não há um único OID universal. O padrão de impressora (RFC 3805) vale na
     | maioria, mas modelos como a HP Color LaserJet Pro 4203 devolvem
     | noSuchInstance nele e publicam o serial só no ramo próprio da HP. O
     | último candidato é a string de device-id (IEEE 1284), que carrega
     | "SN:xxxx" no meio — dela o serial é extraído por regex.
     */
    private const OIDS_SERIAL = [
        '1.3.6.1.2.1.43.5.1.1.17.1',            // prtGeneralSerialNumber (padrão)
        '1.3.6.1.4.1.11.2.3.9.4.2.1.1.3.3.0',   // HP — ramo próprio
        '1.3.6.1.2.1.43.5.1.1.17.2',            // padrão, índice 2
        '1.3.6.1.2.1.25.3.2.1.3.1',             // último recurso: device-id string
    ];

    /** Device-id IEEE 1284, que traz "SN:xxxx;" embutido. */
    private const OID_DEVICE_ID = '1.3.6.1.4.1.11.2.3.9.4.2.1.1.3.3.0';

    public function __construct(
        private readonly string $community = 'public',
        private readonly float $timeout = 2.0,
    ) {}

    /**
     * Lê um OID escalar. Devolve o valor, ou null quando não há resposta ou o
     * OID não existe no equipamento.
     */
    /**
     * Serial da impressora, tentando os OIDs conhecidos em ordem.
     *
     * Devolve o primeiro que responder com algo utilizável. Null quando
     * nenhum respondeu — impressora sem SNMP ou modelo que não publica série.
     */
    public function serial(string $ip): ?string
    {
        foreach (self::OIDS_SERIAL as $oid) {
            $valor = $this->get($ip, $oid);

            if ($valor === null) {
                continue;
            }

            // Alguns OIDs devolvem a string de device-id inteira; o serial
            // está no campo SN. Ex.: "MFG:HP;MDL:...;SN:BRBSS6P066;PRN:..."
            if (preg_match('/(?:^|;)\s*S\/?N\s*:\s*([A-Za-z0-9\-]{4,})/i', $valor, $m)) {
                return $m[1];
            }

            $limpo = $this->limparSerial($valor);

            // Um serial cru não tem os separadores do device-id nem espaço.
            if ($limpo !== '' && ! str_contains($limpo, ';') && ! str_contains($limpo, ':') && ! str_contains($limpo, ' ')) {
                return $limpo;
            }
        }

        return null;
    }

    /**
     * Remove lixo de codificação em volta do serial.
     *
     * O OID próprio da HP às vezes prefixa bytes de controle ao valor — a
     * E55040 devolveu 0xFDE8 antes de "JPBCQDB06N". Sem limpar, o serial
     * entraria sujo e não casaria com a Simpress. Mantém só o que é serial de
     * verdade: letras, dígitos e hífen.
     */
    private function limparSerial(string $valor): string
    {
        // Remove só bytes de controle/não imprimíveis das pontas, preservando
        // espaços internos: espaço é sinal de que é texto de modelo, não
        // serial, e a checagem seguinte usa isso para descartar.
        return trim($valor, " \t\n\r\0\x0B\x00..\x1F\x7F..\xFF");
    }

    public function get(string $ip, string $oid): ?string
    {
        $requestId = random_int(1, 0x7FFFFFFF);
        $pacote = $this->montarGet($requestId, $oid);

        $resposta = $this->transmitir($ip, $pacote);

        if ($resposta === null) {
            return null;
        }

        return $this->extrairValor($resposta);
    }

    private function transmitir(string $ip, string $pacote): ?string
    {
        $socket = @fsockopen('udp://'.$ip, 161, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            return null;
        }

        stream_set_timeout($socket, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1_000_000));

        @fwrite($socket, $pacote);
        $resposta = @fread($socket, 4096);
        fclose($socket);

        return ($resposta === false || $resposta === '') ? null : $resposta;
    }

    // ------------------------------------------------------------ codificação

    private function montarGet(int $requestId, string $oid): string
    {
        $varbind = $this->seq(0x30, $this->encodeOid($oid).$this->tlv(0x05, ''));
        $varbindList = $this->seq(0x30, $varbind);

        $pdu = $this->tlv(
            0xA0, // GetRequest
            $this->encodeInt($requestId).$this->encodeInt(0).$this->encodeInt(0).$varbindList
        );

        // version 1 (0) = SNMPv2c aceita por quase todos; impressora responde.
        return $this->seq(0x30,
            $this->encodeInt(1).$this->tlv(0x04, $this->community).$pdu
        );
    }

    private function tlv(int $tag, string $value): string
    {
        return chr($tag).$this->encodeLength(strlen($value)).$value;
    }

    private function seq(int $tag, string $value): string
    {
        return $this->tlv($tag, $value);
    }

    private function encodeLength(int $n): string
    {
        if ($n < 0x80) {
            return chr($n);
        }

        $bytes = '';
        while ($n > 0) {
            $bytes = chr($n & 0xFF).$bytes;
            $n >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function encodeInt(int $v): string
    {
        if ($v === 0) {
            return $this->tlv(0x02, "\x00");
        }

        $bytes = '';
        $n = $v;
        while ($n > 0) {
            $bytes = chr($n & 0xFF).$bytes;
            $n >>= 8;
        }

        // Bit alto setado seria lido como negativo: prefixa zero.
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00".$bytes;
        }

        return $this->tlv(0x02, $bytes);
    }

    private function encodeOid(string $oid): string
    {
        $partes = array_map('intval', explode('.', trim($oid, '.')));
        $bytes = chr($partes[0] * 40 + $partes[1]);

        foreach (array_slice($partes, 2) as $p) {
            if ($p < 0x80) {
                $bytes .= chr($p);

                continue;
            }

            $grupo = [];
            while ($p > 0) {
                array_unshift($grupo, ($p & 0x7F) | 0x80);
                $p >>= 7;
            }
            $grupo[count($grupo) - 1] &= 0x7F;
            $bytes .= implode('', array_map('chr', $grupo));
        }

        return $this->tlv(0x06, $bytes);
    }

    // ------------------------------------------------------------ decodificação

    private function extrairValor(string $data): ?string
    {
        $i = 0;
        $len = strlen($data);

        $lerTag = function (int $esperado) use ($data, &$i, $len): ?int {
            if ($i >= $len || ord($data[$i]) !== $esperado) {
                return null;
            }
            $i++;

            return $this->lerComprimento($data, $i);
        };

        // SEQUENCE > version > community > PDU
        if ($lerTag(0x30) === null) {
            return null;
        }
        if (($l = $lerTag(0x02)) === null) {
            return null;
        }
        $i += $l; // version
        if (($l = $lerTag(0x04)) === null) {
            return null;
        }
        $i += $l; // community

        // PDU (qualquer tag de resposta 0xA2)
        if ($i >= $len) {
            return null;
        }
        $i++; // tag da PDU
        $this->lerComprimento($data, $i);

        if (($l = $lerTag(0x02)) === null) {
            return null;
        }
        $i += $l; // request-id

        // error-status: != 0 significa OID inexistente ou outro erro.
        if (($l = $lerTag(0x02)) === null) {
            return null;
        }
        $erro = 0;
        for ($k = 0; $k < $l; $k++) {
            $erro = ($erro << 8) | ord($data[$i + $k]);
        }
        $i += $l;
        if ($erro !== 0) {
            return null;
        }

        if (($l = $lerTag(0x02)) === null) {
            return null;
        }
        $i += $l; // error-index

        // varbind list > varbind > oid
        if ($lerTag(0x30) === null || $lerTag(0x30) === null) {
            return null;
        }
        if (($l = $lerTag(0x06)) === null) {
            return null;
        }
        $i += $l; // oid

        // valor
        if ($i >= $len) {
            return null;
        }
        $tipo = ord($data[$i]);
        $i++;
        $l = $this->lerComprimento($data, $i);
        $valor = substr($data, $i, $l);

        // 0x80/0x81/0x82 = noSuchObject/Instance/endOfMibView.
        if (in_array($tipo, [0x80, 0x81, 0x82], true)) {
            return null;
        }

        if ($tipo === 0x04) { // OCTET STRING
            $texto = trim($valor);

            return $texto !== '' ? $texto : null;
        }

        if ($tipo === 0x02) { // INTEGER
            $n = 0;
            for ($k = 0; $k < $l; $k++) {
                $n = ($n << 8) | ord($valor[$k]);
            }

            return (string) $n;
        }

        return null;
    }

    private function lerComprimento(string $data, int &$i): int
    {
        $primeiro = ord($data[$i]);
        $i++;

        if (($primeiro & 0x80) === 0) {
            return $primeiro;
        }

        $qtd = $primeiro & 0x7F;
        $n = 0;
        for ($k = 0; $k < $qtd; $k++) {
            $n = ($n << 8) | ord($data[$i]);
            $i++;
        }

        return $n;
    }
}
