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
        <?php if(! $hasAnyDashboardAccess): ?>
            <section class="rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Visão geral</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-950">Nenhum dashboard encontrado para o seu usuário</h1>
            </section>
        <?php else: ?>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Visão geral</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Dashboard CoreTI</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                    Acompanhamento rápido de acessos, circuitos, unidades e movimentações recentes.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="<?php echo e(route('applications.index')); ?>" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                    Ver aplicativos
                </a>
                <a href="<?php echo e(route('circuits.units')); ?>" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                    Ver circuitos
                </a>
                <?php if($isAdmin): ?>
                    <a href="<?php echo e(route('admin.dashboard')); ?>" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                        Gerenciar usuários
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-stat-grid grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Circuitos cadastrados</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white"><?php echo e(number_format($stats['circuits_total'], 0, ',', '.')); ?></span>
                    <span class="rounded-md bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-800 dark:bg-sky-400/15 dark:text-sky-200"><?php echo e(number_format($stats['operators_total'], 0, ',', '.')); ?> operadoras</span>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Unidades monitoradas</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white"><?php echo e(number_format($stats['units_total'], 0, ',', '.')); ?></span>
                    <span class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200">monitoradas</span>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Usuários ativos</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white"><?php echo e(number_format($stats['users_active'], 0, ',', '.')); ?></span>
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"><?php echo e(number_format($stats['users_total'], 0, ',', '.')); ?> total</span>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Aplicativos</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white"><?php echo e(number_format($stats['applications_total'], 0, ',', '.')); ?></span>
                    <span class="rounded-md bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-800 dark:bg-violet-400/15 dark:text-violet-200">downloads</span>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pendências de acesso</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <span class="text-3xl font-bold text-slate-950 dark:text-white"><?php echo e(number_format($stats['users_pending'], 0, ',', '.')); ?></span>
                    <span class="rounded-md bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-400/15 dark:text-amber-200"><?php echo e(number_format($stats['admins'], 0, ',', '.')); ?> admins</span>
                </div>
            </div>
        </div>

        <div class="dashboard-main-grid grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Circuitos recentes</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/40 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3">Unidade</th>
                                <th class="px-5 py-3">Operadora</th>
                                <th class="px-5 py-3">Serviço</th>
                                <th class="px-5 py-3">Contato</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php $__empty_1 = true; $__currentLoopData = $recentCircuits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $circuit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr>
                                    <td class="px-5 py-4 font-medium text-slate-900 dark:text-slate-100"><?php echo e($circuit->unidade->unidade ?? $circuit->unidades_circuitos); ?></td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300"><?php echo e($circuit->operadora); ?></td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300"><?php echo e($circuit->servico); ?></td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                        <?php if($circuit->contato_whatsapp && $circuit->whatsappUrl()): ?>
                                            <a href="<?php echo e($circuit->whatsappUrl()); ?>" target="_blank" rel="noopener noreferrer" class="underline hover:no-underline">
                                                <?php echo e($circuit->contato); ?>

                                            </a>
                                        <?php else: ?>
                                            <?php echo e($circuit->contato); ?>

                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">Nenhum circuito cadastrado ainda.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid gap-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Operadoras com mais circuitos</h2>
                    <div class="mt-4 space-y-3">
                        <?php $__empty_1 = true; $__currentLoopData = $operatorBreakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $operator): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $percent = $stats['circuits_total'] > 0 ? max(4, round(($operator->total / $stats['circuits_total']) * 100)) : 0;
                            ?>
                            <div>
                                <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                    <span class="truncate font-medium text-slate-700 dark:text-slate-200"><?php echo e($operator->operadora); ?></span>
                                    <span class="text-slate-500 dark:text-slate-400"><?php echo e(number_format($operator->total, 0, ',', '.')); ?></span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-2 rounded-full bg-sky-500" style="width: <?php echo e($percent); ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Sem dados de operadoras.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <?php if($isAdmin): ?>
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Usuários aguardando aprovação</h2>
                    <a href="<?php echo e(route('admin.dashboard')); ?>" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Abrir administração</a>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php $__empty_1 = true; $__currentLoopData = $pendingUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pendingUser): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-100"><?php echo e($pendingUser->name); ?></p>
                                <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo e($pendingUser->email); ?></p>
                            </div>
                            <span class="text-sm text-slate-500 dark:text-slate-400"><?php echo e(optional($pendingUser->created_at)->format('d/m/Y H:i')); ?></span>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <p class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">Nenhum usuário pendente no momento.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
        <?php endif; ?>
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
<?php /**PATH /var/www/html/coreti/resources/views/home.blade.php ENDPATH**/ ?>