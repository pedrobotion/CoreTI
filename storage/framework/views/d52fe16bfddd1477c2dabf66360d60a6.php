<?php
    $feedbackErrors = $errors->all();
    $feedbackType = null;
    $feedbackTitle = null;
    $feedbackMessages = [];

    if (!empty($feedbackErrors)) {
        $feedbackType = 'error';
        $feedbackTitle = 'Não foi possível concluir a ação';
        $feedbackMessages = $feedbackErrors;
    } elseif (session('error')) {
        $feedbackType = 'error';
        $feedbackTitle = 'Não foi possível concluir a ação';
        $feedbackMessages = [(string) session('error')];
    } elseif (session('success')) {
        $feedbackType = 'success';
        $feedbackTitle = 'Ação concluída com sucesso';
        $feedbackMessages = [(string) session('success')];
    }
?>

<?php if($feedbackType && !empty($feedbackMessages)): ?>
    <div
        x-data="{ open: true }"
        x-cloak
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-950/50 px-4"
        @keydown.escape.window="open = false"
    >
        <div class="w-full max-w-xl rounded-xl border bg-white p-5 shadow-2xl" :class="{
            'border-red-200': <?php echo \Illuminate\Support\Js::from($feedbackType === 'error')->toHtml() ?>,
            'border-emerald-200': <?php echo \Illuminate\Support\Js::from($feedbackType === 'success')->toHtml() ?>
        }">
            <h3 class="text-lg font-bold" :class="{
                'text-red-700': <?php echo \Illuminate\Support\Js::from($feedbackType === 'error')->toHtml() ?>,
                'text-emerald-700': <?php echo \Illuminate\Support\Js::from($feedbackType === 'success')->toHtml() ?>
            }"><?php echo e($feedbackTitle); ?></h3>

            <div class="mt-3 space-y-2 text-sm text-slate-700 max-h-72 overflow-y-auto pr-1">
                <?php $__currentLoopData = $feedbackMessages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <p>• <?php echo e($message); ?></p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="button" @click="open = false" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Entendi
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php /**PATH /var/www/html/coreti/resources/views/layouts/partials/feedback-modal.blade.php ENDPATH**/ ?>