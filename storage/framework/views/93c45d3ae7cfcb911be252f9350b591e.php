<div
    x-data="{
        open: false,
        message: 'Confirma esta ação?',
        formEl: null,
        ask(detail) {
            this.message = detail?.message || 'Confirma esta ação?';
            this.formEl = detail?.form || null;
            this.open = true;
        },
        cancel() {
            this.open = false;
            this.formEl = null;
        },
        confirm() {
            if (this.formEl) this.formEl.submit();
            this.cancel();
        }
    }"
    @coreti-confirm.window="ask($event.detail)"
    x-cloak
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[95] bg-slate-900/40"></div>
    <div x-show="open" x-transition class="fixed inset-0 z-[96] flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
            <p class="text-sm font-medium text-slate-800" x-text="message"></p>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="cancel()" class="btn-coreti-secondary">Cancelar</button>
                <button type="button" @click="confirm()" class="btn-coreti-primary">Confirmar</button>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /var/www/html/coreti/resources/views/layouts/partials/confirm-dialog.blade.php ENDPATH**/ ?>