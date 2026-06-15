<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Bancada de Serviços</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Impressão de Etiqueta</h1>
            </div>
            <a href="{{ route('bancada-servicos.assets') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Voltar</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" x-data="{
            unidade: @js($asset->unidade_setor ?? ''),
            observacao: @js($asset->observacao ?? ''),
            imprimir() {
                const printer = 'ZDesigner ZD220-203dpi ZPL';
                const unidade = (this.unidade || '').toUpperCase().slice(0, 40);
                const observacao = (this.observacao || '').toUpperCase().slice(0, 50);
                const zpl = '^XA^PW720^LL240^CF0,30^FO30,30^FDUNIDADE:^FS^FO200,30^FD' + unidade + '^FS^FO30,90^FDOBS:^FS^FO130,90^FD' + observacao + '^FS^XZ';
                const notify = (type, message) => window.dispatchEvent(new CustomEvent('coreti-toast', { detail: { type, message } }));

                if (!window.qz || !qz.websocket) {
                    notify('error', 'QZ Tray não carregado.');
                    return;
                }

                qz.websocket.connect()
                    .then(() => qz.printers.find(printer))
                    .then(found => qz.print(qz.configs.create(found), [zpl]))
                    .then(() => notify('success', 'Etiqueta enviada para impressão.'))
                    .catch(err => notify('error', 'Falha na impressão: ' + err));
            }
        }">
            <script src="{{ asset('vendor/qz-tray.js') }}"></script>
            <div class="grid gap-3">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Unidade</label>
                    <input x-model="unidade" maxlength="40" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Observação</label>
                    <textarea x-model="observacao" maxlength="50" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm"></textarea>
                </div>
                <div class="pt-1">
                    <button type="button" @click="imprimir" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white">Imprimir etiqueta</button>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
