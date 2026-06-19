<script src="{{ asset('vendor/qz-tray.js') }}"></script>
<script>
    function bancadaLabelPrinter() {
        return {
            printerName: 'ZDesigner ZD220-203dpi ZPL',
            sanitize(value, maxLen = 64) {
                return String(value || '')
                    .replace(/[\r\n]+/g, ' ')
                    .replace(/[–—]/g, '-')
                    .replace(/\s+/g, ' ')
                    .replace(/[\^~\\]/g, ' ')
                    .trim()
                    .slice(0, maxLen);
            },
            truncateLabelText(value, limit) {
                const text = this.sanitize(value, 160);
                const chars = Array.from(text);
                if (chars.length <= limit) {
                    return text;
                }
                return chars.slice(0, limit).join('').trimEnd() + '...';
            },
            formatLabelLocation(value) {
                const raw = this.sanitize(value, 160);

                if (/^sede\s*-\s*trr\b/i.test(raw)) {
                    return 'Sede - TRR';
                }

                const withoutPrefix = raw
                    .replace(/^(unidades?|unidade|sede)\s*-\s*/i, '')
                    .trim();

                const replacements = [
                    [/^(?:cd\s*-\s*)?centro de distribuição de insumos$/i, 'CD Insumos'],
                    [/^centro de distribuição$/i, 'Centro Distrib.'],
                    [/^ubc\s*-\s*unidade de beneficiamento de café$/i, 'UBC Café'],
                    [/^unidade de beneficiamento de café$/i, 'UBC Café'],
                    [/^originação e operação de cereais$/i, 'Orig. Oper. Cereais'],
                    [/^superintendência logística integrada$/i, 'Sup. Logística'],
                ];

                return replacements.reduce((text, [pattern, replacement]) => text.replace(pattern, replacement), withoutPrefix);
            },
            formatLabelObservation(value) {
                const text = this.sanitize(value, 160);
                if (text === '') {
                    return '-';
                }

                return this.truncateLabelText(text, 40);
            },
            encodeZplText(value) {
                const bytes = new TextEncoder().encode(String(value || ''));
                return Array.from(bytes)
                    .map((byte) => '\\' + byte.toString(16).toUpperCase().padStart(2, '0'))
                    .join('');
            },
            notify(type, message) {
                window.dispatchEvent(new CustomEvent('coreti-toast', { detail: { type, message } }));
            },
            buildZpl(local, chegada, observacao) {
                const localBase = this.formatLabelLocation(local);
                const localLength = Array.from(localBase).length;
                const localDisplay = this.truncateLabelText(localBase, localLength > 28 ? 28 : localLength);
                const c = this.sanitize(chegada, 16);
                const o = this.formatLabelObservation(observacao);
                const localHex = this.encodeZplText(localDisplay);
                const chegadaHex = this.encodeZplText(c);
                const obsHex = this.encodeZplText(o);
                const localIsLong = localLength > 22;
                const localField = localIsLong
                    ? '^FT196,84^A0N,30,30^FB590,1,0,L,0^FH\\^FD' + localHex + '^FS'
                    : '^FT196,84^A0N,34,34^FB590,1,0,L,0^FH\\^FD' + localHex + '^FS';

                const printData = [
                    '^XA',
                    '^LT0',
                    '^LH0,0',
                    '^CI28',
                    '^MMT',
                    '^PW831',
                    '^LL290',
                    '^LS0',
                    '^FO20,10^GB790,260,5^FS',
                    '^FT394,44^A0N,34,34^FDTI^FS',
                    '^FT42,84^A0N,38,36^FDLocal:^FS',
                    '^FT42,130^A0N,38,36^FDChegada:^FS',
                    '^FT42,176^A0N,38,36^FDOBS:^FS',
                    localField,
                    '^FT236,130^A0N,36,34^FH\\^FD' + chegadaHex + '^FS',
                    '^FT150,214^A0N,32,30^FB640,2,6,L,0^FH\\^FD' + obsHex + '^FS',
                    '^PQ1,0,1,Y',
                    '^XZ'
                ];

                return printData.join('');
            },
            async print(local, chegada, observacao) {
                if (!window.qz || !qz.websocket) {
                    this.notify('error', 'QZ Tray não carregado.');
                    return;
                }

                const zpl = this.buildZpl(local, chegada, observacao);

                try {
                    if (!qz.websocket.isActive()) {
                        await qz.websocket.connect();
                    }
                    const found = await qz.printers.find(this.printerName);
                    await qz.print(qz.configs.create(found), [zpl]);
                    this.notify('success', 'Etiqueta enviada para impressão.');
                } catch (error) {
                    this.notify('error', 'Falha na impressão: ' + error);
                }
            }
        };
    }
</script>
