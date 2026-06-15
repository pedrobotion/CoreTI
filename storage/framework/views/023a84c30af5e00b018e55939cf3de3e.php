<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Administrativo</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Painel Administrativo</h1>
            <p class="mt-2 text-sm text-slate-600">Resumo de pendências e atalhos operacionais.</p>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Consulta de Equipamento</h2>
                    <p class="mt-1 text-sm text-slate-600">Busque pela plaqueta para abrir a visão completa do equipamento.</p>
                </div>
                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Busca global</span>
            </div>

            <form method="GET" action="<?php echo e(route('administrativo.visao-geral')); ?>" class="mt-4 space-y-3">
                <input
                    type="text"
                    name="equipment_plaqueta"
                    value="<?php echo e(request('equipment_plaqueta', '')); ?>"
                    placeholder="Digite a plaqueta do equipamento"
                    class="h-10 w-full rounded-md border-slate-300 text-sm"
                >
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex h-10 flex-1 items-center justify-center rounded-md border border-[#033151] bg-[#033151] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">Buscar equipamento</button>
                    <a href="<?php echo e(route('administrativo.visao-geral')); ?>" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Limpar</a>
                </div>
            </form>
        </section>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH /var/www/html/coreti/resources/views/panels/administrativo/painel.blade.php ENDPATH**/ ?>