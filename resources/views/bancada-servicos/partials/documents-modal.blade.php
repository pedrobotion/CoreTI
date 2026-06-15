{{-- Modal de documentos/anexos do equipamento --}}
<div
    x-cloak
    x-data="{ documentsModalOpen:false, documentsList:null, documentEquipmentTag:'', loadingDocuments:false, errorMessage:'', openFromEvent(e){ this.documentsList = null; this.documentEquipmentTag = e.detail.plaqueta; this.loadingDocuments = true; this.errorMessage = ''; this.documentsModalOpen = true; fetch(e.detail.url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' }).then(async r => { if (!r.ok) { const body = await r.text().catch(() => ''); console.error('Erro ao carregar documentos', { status: r.status, statusText: r.statusText, body }); throw new Error(`Erro HTTP ${r.status}`); } return r.json(); }).then(d=>{ this.documentsList = d.documents || []; }).catch((error)=>{ console.error(error); this.documentsList = []; this.errorMessage = 'Erro ao carregar documentos do equipamento.'; }).finally(()=>{ this.loadingDocuments = false; }) } }"
    x-show="documentsModalOpen"
    x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4"
    @keydown.escape.window="documentsModalOpen=false"
    @open-documents.window="openFromEvent($event)"
>
    <div
        class="w-full max-w-4xl max-h-[90vh] overflow-hidden rounded-lg bg-white shadow-2xl flex flex-col"
        @click.outside="documentsModalOpen=false"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 shrink-0">
            <h3 class="text-lg font-bold text-slate-900">
                Documentos do equipamento
                <span class="text-[#033151] font-bold" x-text="documentEquipmentTag"></span>
            </h3>
            <button
                type="button"
                @click="documentsModalOpen=false"
                class="text-sm font-semibold text-slate-600 hover:text-slate-900"
            >
                Fechar
            </button>
        </div>

        {{-- Content --}}
        <div class="overflow-y-auto flex-1">
            <div x-show="documentsList && documentsList.length > 0" x-cloak class="p-5">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 text-left text-xs font-semibold uppercase text-slate-700">
                            <tr>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Nome do arquivo</th>
                                <th class="px-4 py-3">Data de upload</th>
                                <th class="px-4 py-3">Usuário</th>
                                <th class="px-4 py-3">Evento/Ação</th>
                                <th class="px-4 py-3 text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <template x-for="doc in documentsList" :key="doc.id">
                                <tr class="odd:bg-slate-50 even:bg-white hover:bg-blue-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800" x-text="doc.type_label"></span>
                                    </td>
                                    <td class="px-4 py-3 break-words">
                                        <span x-text="doc.original_name"></span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span x-text="doc.uploaded_at_formatted"></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span x-text="doc.uploaded_by_name"></span>
                                    </td>
                                    <td class="px-4 py-3 break-words">
                                        <span x-text="doc.event_action || '-'"></span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a
                                            :href="doc.download_url"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-slate-300 text-emerald-600 hover:bg-slate-50 hover:text-emerald-800 transition-colors"
                                            :title="'Baixar ' + doc.original_name"
                                            :aria-label="'Baixar ' + doc.original_name"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M12 4v12m0 0l-4-4m4 4l4-4" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Loading state --}}
            <div x-show="loadingDocuments" x-cloak class="flex flex-col items-center justify-center p-12">
                <svg class="animate-spin h-10 w-10 text-slate-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <p class="text-center text-slate-500 font-medium">Carregando documentos...</p>
            </div>

            {{-- Empty / error state --}}
            <div x-show="!loadingDocuments && (!documentsList || documentsList.length === 0)" x-cloak class="flex flex-col items-center justify-center p-12">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-16 w-16 text-slate-300 mb-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z" />
                </svg>
                <p class="text-center text-slate-500 font-medium" x-text="errorMessage || 'Nenhum documento anexado a este equipamento.'"></p>
            </div>
        </div>

        {{-- Footer --}}
        <div class="border-t border-slate-200 px-5 py-3 bg-slate-50 text-xs text-slate-500 shrink-0">
            <span x-text="documentsList ? `Total: ${documentsList.length} documento(s)` : 'Carregando...'"></span>
        </div>
    </div>
</div>
