<section class="rounded-lg border-2 border-rose-200 bg-rose-50 p-5 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-bold text-rose-900">Pendências Terceiros</h2>
        <span class="rounded-md bg-rose-200 px-2 py-1 text-xs font-semibold text-rose-900"><?php echo e($pendingThirdParty->count()); ?> pendências</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] text-sm">
            <thead class="bg-slate-900 text-left text-xs font-semibold uppercase text-white">
                <tr>
                    <th class="px-3 py-2">Plaqueta</th>
                    <th class="px-3 py-2">Tipo</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Fluxo Terceiros</th>
                    <th class="px-3 py-2">Unidade/Setor</th>
                    <th class="px-3 py-2">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php $__empty_1 = true; $__currentLoopData = $pendingThirdParty; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $thirdPartyStage = $item->thirdPartyWorkflowStage();
                        $remessaAttachment = $item->thirdPartyLatestAttachmentOfType('nota_remessa');
                        $infoStatusLabel = match ($thirdPartyStage) {
                            'aguardando_informacoes' => 'Aguardando terceiro',
                            'aguardando_retorno_fisico' => 'Aguardando retorno físico',
                            default => 'Pendente ADM',
                        };
                        $infoStatusDescription = match ($thirdPartyStage) {
                            'aguardando_informacoes' => 'Enviado / aguardando informações',
                            'aguardando_retorno_fisico' => 'Informações recebidas / aguardando retorno físico',
                            default => 'Aguardando envio ao terceiro',
                        };
                        $infoStatusClass = match ($thirdPartyStage) {
                            'aguardando_informacoes' => 'bg-amber-100 text-amber-800',
                            'aguardando_retorno_fisico' => 'bg-blue-100 text-blue-800',
                            default => 'bg-rose-100 text-rose-800',
                        };
                        $actionLabel = match ($thirdPartyStage) {
                            'aguardando_informacoes' => 'Orçamento realizado',
                            'aguardando_retorno_fisico' => 'Registrar retorno do equipamento',
                            default => 'Registrar envio',
                        };
                    ?>
                    <tr x-data="{ modalOpen: false }">
                        <td class="px-3 py-2"><?php echo e($item->plaqueta); ?></td>
                        <td class="px-3 py-2"><?php echo e($item->tipo_equipamento ?: '-'); ?></td>
                        <td class="px-3 py-2"><?php echo e($item->status); ?></td>
                        <td class="px-3 py-2">
                            <div class="inline-flex flex-col items-start gap-0.5">
                                <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold <?php echo e($infoStatusClass); ?>"><?php echo e($infoStatusLabel); ?></span>
                                <span class="text-[10px] font-medium text-slate-500"><?php echo e($infoStatusDescription); ?></span>
                            </div>
                        </td>
                        <td class="px-3 py-2"><?php echo e($item->unidade_setor); ?></td>
                        <td class="px-3 py-2">
                            <button type="button" @click.stop="modalOpen = true" class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90"><?php echo e($actionLabel); ?></button>

                            <template x-teleport="body">
                                <div
                                    x-cloak
                                    x-show="modalOpen"
                                    x-transition.opacity
                                    class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-slate-900/60 px-3 py-3 sm:px-4 sm:py-4"
                                    @keydown.escape.window="modalOpen=false"
                                    @click.self="modalOpen=false"
                                >
                                    <div class="w-full max-w-3xl max-h-[calc(100vh-1.5rem)] overflow-hidden rounded-lg bg-white shadow-2xl sm:max-h-[calc(100vh-2rem)]">
                                        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                            <h3 class="text-lg font-bold text-slate-900">Processo administrativo | <?php echo e($item->plaqueta); ?></h3>
                                            <button type="button" @click="modalOpen=false" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fechar</button>
                                        </div>

                                        <div class="max-h-[calc(100vh-8rem)] overflow-y-auto">
                                            <?php if($thirdPartyStage === 'aguardando_envio'): ?>
                                                <form
                                                    x-data="{ selectedCompanyCnpj: <?php echo \Illuminate\Support\Js::from($item->terceiros_cnpj ?? '')->toHtml() ?> }"
                                                    x-init="$nextTick(() => { const selected = $refs.thirdPartyCompanySelect?.selectedOptions?.[0]; if (!selectedCompanyCnpj) selectedCompanyCnpj = selected?.dataset?.cnpj || ''; })"
                                                    method="POST"
                                                    enctype="multipart/form-data"
                                                    action="<?php echo e(route('bancada-servicos.assets.administrative.process', $item)); ?>"
                                                    class="grid gap-3 px-5 py-4 sm:grid-cols-2"
                                                >
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('PATCH'); ?>
                                                    <input type="hidden" name="action" value="terceiros_envio">

                                                    <div class="sm:col-span-2">
                                                        <label class="text-sm font-medium">Problema</label>
                                                        <textarea rows="2" readonly class="mt-1 w-full rounded-md border-slate-300 bg-slate-100 text-sm text-slate-700"><?php echo e($item->terceiros_problema ?: '-'); ?></textarea>
                                                    </div>
                                                    <div>
                                                        <label class="text-sm font-medium">Empresa</label>
                                                        <select
                                                            x-ref="thirdPartyCompanySelect"
                                                            name="terceiros_empresa"
                                                            data-third-party-company-select
                                                            @change="selectedCompanyCnpj = $event.target.selectedOptions[0]?.dataset?.cnpj || ''"
                                                            class="mt-1 w-full rounded-md border-slate-300 text-sm"
                                                        >
                                                            <option value="">Selecione</option>
                                                            <?php $__currentLoopData = ($thirdPartyCompanies ?? collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <option value="<?php echo e($company->name); ?>" data-cnpj="<?php echo e($company->cnpj); ?>" <?php if($item->terceiros_empresa === $company->name): echo 'selected'; endif; ?>><?php echo e($company->name); ?></option>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="text-sm font-medium">CNPJ</label>
                                                        <input
                                                            name="terceiros_cnpj"
                                                            data-third-party-cnpj-input
                                                            x-model="selectedCompanyCnpj"
                                                            readonly
                                                            class="mt-1 w-full rounded-md border-slate-300 bg-slate-100 text-sm text-slate-700"
                                                        >
                                                    </div>
                                                    <div>
                                                        <label class="text-sm font-medium">Nota remessa</label>
                                                        <input name="terceiros_nota_remessa" value="<?php echo e($item->terceiros_nota_remessa); ?>" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="text-sm font-medium">Observações</label>
                                                        <textarea name="terceiros_observacoes" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm"><?php echo e($item->terceiros_observacoes); ?></textarea>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="text-sm font-medium">Nota de remessa</label>
                                                        <input type="file" name="terceiros_orcamento_anexo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                    </div>
                                                    <div class="sm:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                                                        <button type="button" @click="modalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                                        <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Registrar envio</button>
                                                    </div>
                                                </form>
                                            <?php elseif($thirdPartyStage === 'aguardando_informacoes'): ?>
                                                <div class="grid gap-4 px-5 py-4">
                                                    <div class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                                                        <div class="sm:col-span-2">
                                                            <p class="text-sm font-semibold text-slate-900">Dados do envio ao terceiro</p>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Plaqueta</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->plaqueta); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->tipo_equipamento ?: '-'); ?></div>
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Problema</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 whitespace-pre-wrap"><?php echo e($item->terceiros_problema ?: '-'); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Empresa</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_empresa ?: '-'); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">CNPJ</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_cnpj ?: '-'); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Nota remessa</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_nota_remessa ?: '-'); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">OS</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_os_numero ?: '-'); ?></div>
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Observações</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 whitespace-pre-wrap"><?php echo e($item->terceiros_observacoes ?: '-'); ?></div>
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <?php if($remessaAttachment): ?>
                                                                <a
                                                                    href="<?php echo e(route('bancada-servicos.attachments.download', $remessaAttachment)); ?>"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-white px-4 text-sm font-semibold text-[#033151] hover:bg-slate-50"
                                                                >
                                                                    Visualizar nota de remessa
                                                                </a>
                                                            <?php else: ?>
                                                                <p class="text-sm text-slate-500">Nenhum anexo de remessa disponível.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="rounded-md border border-slate-200 bg-white p-4" x-data="{
                                                        informacaoRecebida: <?php echo \Illuminate\Support\Js::from(in_array((string) $item->terceiros_resultado, ['sem_conserto', 'negada', 'reprovada', 'reprovado'], true) ? 'sem_conserto' : '')->toHtml() ?>,
                                                        showValor() {
                                                            return this.informacaoRecebida === 'orcamento_informado';
                                                        },
                                                        actionType() {
                                                            if (this.informacaoRecebida === 'sem_conserto') {
                                                                return 'terceiros_retorno_info_negativo';
                                                            }

                                                            return 'terceiros_retorno_info_positivo';
                                                        },
                                                    }">
                                                        <p class="text-sm font-semibold text-slate-900">Informações do reparo</p>
                                                        <div class="mt-3 max-w-sm">
                                                            <label class="text-sm font-medium text-slate-700">Informação recebida do terceiro</label>
                                                            <select x-model="informacaoRecebida" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                                <option value="">Selecione</option>
                                                                <option value="orcamento_informado">Orçamento informado</option>
                                                                <option value="sem_conserto">Sem conserto</option>
                                                            </select>
                                                        </div>

                                                        <form x-cloak x-show="informacaoRecebida" method="POST" enctype="multipart/form-data" action="<?php echo e(route('bancada-servicos.assets.administrative.process', $item)); ?>" class="mt-4 grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                                                            <?php echo csrf_field(); ?>
                                                            <?php echo method_field('PATCH'); ?>
                                                            <input type="hidden" name="action" :value="actionType()">

                                                            <div>
                                                                <label class="text-sm font-medium">Número da OS</label>
                                                                <input name="terceiros_os_numero" value="<?php echo e($item->terceiros_os_numero); ?>" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                            </div>
                                                            <div x-show="showValor()">
                                                                <label class="text-sm font-medium">Valor do reparo / orçamento</label>
                                                                <input name="terceiros_valor_reparo" placeholder="0,00" value="<?php echo e($item->terceiros_valor_reparo !== null ? number_format((float) $item->terceiros_valor_reparo, 2, ',', '.') : ''); ?>" required class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label class="text-sm font-medium">Observação do orçamento / reparo</label>
                                                                <textarea name="terceiros_observacoes" rows="2" class="mt-1 w-full rounded-md border-slate-300 text-sm"><?php echo e($item->terceiros_observacoes); ?></textarea>
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label class="text-sm font-medium">Anexo do orçamento</label>
                                                                <input type="file" name="terceiros_orcamento_anexo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                                            </div>
                                                            <div class="sm:col-span-2 flex justify-end">
                                                                <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Salvar orçamento</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php elseif($thirdPartyStage === 'aguardando_retorno_fisico'): ?>
                                                <div class="grid gap-4 px-5 py-4">
                                                    <div class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                                                        <div class="sm:col-span-2">
                                                            <p class="text-sm font-semibold text-slate-900">Dados do envio ao terceiro</p>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Plaqueta</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->plaqueta); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->tipo_equipamento ?: '-'); ?></div>
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Problema</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 whitespace-pre-wrap"><?php echo e($item->terceiros_problema ?: '-'); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Empresa</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_empresa ?: '-'); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">CNPJ</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_cnpj ?: '-'); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Nota remessa</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_nota_remessa ?: '-'); ?></div>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs font-semibold uppercase text-slate-500">OS</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_os_numero ?: '-'); ?></div>
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <label class="text-xs font-semibold uppercase text-slate-500">Observações</label>
                                                            <div class="mt-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 whitespace-pre-wrap"><?php echo e($item->terceiros_observacoes ?: '-'); ?></div>
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <?php if($remessaAttachment): ?>
                                                                <a
                                                                    href="<?php echo e(route('bancada-servicos.attachments.download', $remessaAttachment)); ?>"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-white px-4 text-sm font-semibold text-[#033151] hover:bg-slate-50"
                                                                >
                                                                    Visualizar nota de remessa
                                                                </a>
                                                            <?php else: ?>
                                                                <p class="text-sm text-slate-500">Nenhum anexo de remessa disponível.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="rounded-md border border-slate-200 bg-white p-4">
                                                        <p class="text-sm font-semibold text-slate-900">Registrar retorno do equipamento</p>
                                                        <form method="POST" action="<?php echo e(route('bancada-servicos.assets.administrative.process', $item)); ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                                                            <?php echo csrf_field(); ?>
                                                            <?php echo method_field('PATCH'); ?>
                                                            <input type="hidden" name="action" value="terceiros_retorno_fisico">
                                                            <div>
                                                                <label class="text-sm font-medium">Plaqueta</label>
                                                                <div class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"><?php echo e($item->plaqueta); ?></div>
                                                            </div>
                                                            <div>
                                                                <label class="text-sm font-medium">Empresa</label>
                                                                <div class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_empresa ?: '-'); ?></div>
                                                            </div>
                                                            <div>
                                                                <label class="text-sm font-medium">Informação recebida do terceiro</label>
                                                                <div class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                                                <?php echo e(in_array((string) $item->terceiros_resultado, ['aprovada', 'aprovado'], true) ? 'Orçamento realizado' : (in_array((string) $item->terceiros_resultado, ['sem_conserto', 'negada', 'reprovada', 'reprovado'], true) ? 'Sem conserto' : 'Não informado')); ?>

                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="text-sm font-medium">OS da empresa</label>
                                                                <div class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"><?php echo e($item->terceiros_os_numero ?: '-'); ?></div>
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label class="text-sm font-medium">Observação</label>
                                                                <textarea name="terceiros_retorno_fisico_observacao" rows="3" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Observação opcional"></textarea>
                                                            </div>
                                                            <div class="sm:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-3">
                                                                <button type="button" @click="modalOpen=false" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                                                                <button class="inline-flex min-h-10 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Confirmar retorno na TI</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="px-5 py-4 text-sm text-slate-500">Sem ação disponível para este registro.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-slate-500">Sem pendências de terceiros no momento.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php /**PATH /var/www/html/coreti/resources/views/panels/administrativo/partials/_terceiros.blade.php ENDPATH**/ ?>