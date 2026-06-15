<?php
    $user = Auth::user();
?>

<nav
    x-data="{
        profileOpen: false,
        mobileMenuOpen: false,
        theme: window.getTheme ? window.getTheme() : 'system',
        circuitsOpen: false,
        adminOpen: false,
        indicadoresOpen: false,
        unidadeDigitalOpen: false,
        monitoramentoOpen: false,
        setTheme(mode) {
            this.theme = mode;
            if (window.setTheme) {
                window.setTheme(mode);
            }
        }
    }"
>
    <header class="top-header bg-white">
        <div class="header-bar flex h-full items-center gap-4 px-4 sm:px-6 lg:px-8">
            <div class="header-brand flex shrink-0 items-center">
                <a href="<?php echo e(route('dashboard')); ?>" class="inline-flex items-center">
                    <img
                        src="<?php echo e(asset('images/branding/cocari-logo-black.png')); ?>"
                        alt="Cocari"
                        class="cocari-logo cocari-logo--black brand-logo"
                    >
                    <img
                        src="<?php echo e(asset('images/branding/cocari-logo-white.png')); ?>"
                        alt="Cocari"
                        class="cocari-logo cocari-logo--white brand-logo"
                    >
                </a>
            </div>

            <div class="nav-actions-fixed ml-auto flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    @click="mobileMenuOpen = true"
                    class="mobile-menu-trigger rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm"
                >
                    Menu
                </button>

                <div class="theme-switcher flex">
                    <button type="button" class="theme-toggle" :class="{ 'theme-toggle-active': theme === 'light' }" @click="setTheme('light')" aria-label="Ativar tema claro">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.2m0 13.6V21m9-9h-2.2M5.2 12H3m14.164 6.164-1.556-1.556M8.392 7.392 6.836 5.836m10.328 0-1.556 1.556M8.392 16.608l-1.556 1.556M15.5 12A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5Z" />
                        </svg>
                    </button>
                    <button type="button" class="theme-toggle" :class="{ 'theme-toggle-active': theme === 'dark' }" @click="setTheme('dark')" aria-label="Ativar tema escuro">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.8A8.98 8.98 0 0 1 11.2 3a9 9 0 1 0 9.8 9.8Z" />
                        </svg>
                    </button>
                    <button type="button" class="theme-toggle" :class="{ 'theme-toggle-active': theme === 'system' }" @click="setTheme('system')" aria-label="Usar tema do sistema">
                        Auto
                    </button>
                </div>

                <div class="relative">
                    <button
                        type="button"
                        @click="profileOpen = !profileOpen"
                        class="profile-button flex items-center justify-center overflow-hidden rounded-full border border-slate-300 bg-white ring-2 ring-slate-200 hover:bg-slate-100"
                        aria-label="Abrir menu do perfil"
                    >
                        <img
                            src="<?php echo e(asset('images/ui/profile-avatar.png')); ?>"
                            alt="Perfil"
                            class="profile-avatar"
                        >
                    </button>

                    <div
                        x-cloak
                        x-show="profileOpen"
                        x-transition
                        @click.outside="profileOpen = false"
                        class="profile-dropdown absolute mt-2 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                    >
                        <div class="border-b border-slate-100 px-4 py-2">
                            <p class="truncate text-sm font-medium text-slate-900"><?php echo e($user->name); ?></p>
                            <p class="truncate text-xs text-slate-500"><?php echo e($user->email); ?></p>
                        </div>

                        <a
                            href="<?php echo e(route('profile.edit')); ?>"
                            class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                        >
                            Perfil
                        </a>

                        <?php if($user->role === 'admin'): ?>
                            <a
                                href="<?php echo e(route('admin.dashboard')); ?>"
                                class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"
                            >
                                Administração
                            </a>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo e(route('logout')); ?>">
                            <?php echo csrf_field(); ?>
                            <button
                                type="submit"
                                class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                            >
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <aside class="desktop-sidebar fixed bottom-0 left-0 top-0 z-40 w-64 shadow-sm">
        <?php echo $__env->make('layouts.partials.sidebar-menu', ['containerClass' => 'coreti-sidebar-shell'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </aside>

    <div
        x-cloak
        x-show="mobileMenuOpen"
        x-transition.opacity
        @click="mobileMenuOpen = false"
        class="mobile-overlay fixed inset-0 z-40 bg-slate-900/30"
    ></div>

    <aside
        class="mobile-sidebar mobile-sidebar-position corp-sidebar fixed bottom-0 left-0 z-50 w-[88vw] max-w-xs border-r border-slate-200 shadow-xl"
        :style="mobileMenuOpen ? 'transform: translateX(0%); transition: transform .2s ease;' : 'transform: translateX(-100%); transition: transform .2s ease;'"
        @click.outside="mobileMenuOpen = false"
    >
        <?php echo $__env->make('layouts.partials.sidebar-menu', ['containerClass' => 'coreti-sidebar-shell'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </aside>
</nav>
<?php /**PATH /var/www/html/coreti/resources/views/layouts/navigation.blade.php ENDPATH**/ ?>