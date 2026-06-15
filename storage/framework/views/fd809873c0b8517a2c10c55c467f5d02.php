<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
        <meta name="theme-color" content="#f8fafc">

        <title><?php echo e(config('app.name', 'Laravel')); ?></title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <script>
            (() => {
                localStorage.setItem('coreti-theme', 'light');
                document.documentElement.classList.remove('dark');
                document.documentElement.dataset.theme = 'light';
            })();
        </script>

        <!-- Scripts -->
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
        <?php echo $__env->make('layouts.partials.button-theme', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </head>
    <body class="site-body bg-slate-100 font-sans antialiased">
        <div class="min-h-screen">
            <?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('layouts.partials.feedback-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('layouts.partials.confirm-dialog', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <div class="content-shell">
                <!-- Page Content -->
                <main class="p-4 sm:p-6 lg:p-8">
                    <?php echo e($slot); ?>

                </main>
            </div>
        </div>
        <script>
            document.addEventListener('submit', function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) return;
                const message = form.dataset.confirmMessage;
                if (!message || form.dataset.confirmed === '1') return;
                event.preventDefault();
                window.dispatchEvent(new CustomEvent('coreti-confirm', { detail: { message, form } }));
            }, true);
        </script>
    </body>
</html>
<?php /**PATH /var/www/html/coreti/resources/views/layouts/app.blade.php ENDPATH**/ ?>