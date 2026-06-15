<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <div><p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Administrativo</p><h1 class="mt-1 text-2xl font-bold text-slate-950">Entrada Fiscal</h1></div>
        @include('panels.administrativo.partials._entrada-fiscal')
    </div>
    <script>
        function notaEntradaForm(initialValue) {
            return {
                valorDisplay: '', valorRaw: '',
                init() {
                    const raw = initialValue === null || initialValue === '' ? '' : Number(initialValue).toFixed(2);
                    this.valorRaw = raw;
                    this.valorDisplay = raw === '' ? '' : Number(raw).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                formatMoney() {
                    const digits = (this.valorDisplay || '').replace(/\D/g, '');
                    if (!digits) { this.valorDisplay = ''; this.valorRaw = ''; return; }
                    const amount = Number(digits) / 100;
                    this.valorRaw = amount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    this.valorDisplay = this.valorRaw;
                },
                beforeSubmit() { if (!this.valorRaw) { this.valorRaw = ''; } }
            }
        }
    </script>
</x-app-layout>
