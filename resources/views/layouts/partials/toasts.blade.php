@php
    $toastItems = [];

    if (session('success')) {
        $toastItems[] = ['type' => 'success', 'message' => session('success')];
    }
    if (session('error')) {
        $toastItems[] = ['type' => 'error', 'message' => session('error')];
    }
    if (session('warning')) {
        $toastItems[] = ['type' => 'warning', 'message' => session('warning')];
    }
    if (session('info')) {
        $toastItems[] = ['type' => 'info', 'message' => session('info')];
    }
    if ($errors->any()) {
        $toastItems[] = ['type' => 'error', 'message' => $errors->first()];
    }
@endphp

<div
    x-data="{
        items: @js($toastItems),
        addToast(payload) {
            if (!payload || !payload.message) return;
            const item = { type: payload.type || 'info', message: payload.message };
            this.items.push(item);
            const ttl = item.type === 'error' ? 7000 : 4500;
            setTimeout(() => this.dismiss(item), ttl);
        },
        init() {
            this.items.forEach((item) => {
                const ttl = item.type === 'error' ? 7000 : 4500;
                setTimeout(() => this.dismiss(item), ttl);
            });
        },
        dismiss(item) {
            this.items = this.items.filter(i => i !== item);
        }
    }"
    @coreti-toast.window="addToast($event.detail)"
    class="pointer-events-none fixed top-4 z-[9999] flex w-[min(92vw,520px)] flex-col gap-2
           left-1/2 -translate-x-1/2
           md:left-72 md:right-6 md:w-auto md:max-w-[520px] md:translate-x-0"
>
    <template x-for="item in items" :key="item.message + item.type + Math.random()">
        <div
            x-transition.opacity.duration.250ms
            class="pointer-events-auto rounded-lg border px-4 py-3 text-sm font-medium shadow-lg"
            :class="{
                'border-emerald-200 bg-emerald-50 text-emerald-900': item.type === 'success',
                'border-red-200 bg-red-50 text-red-900': item.type === 'error',
                'border-amber-200 bg-amber-50 text-amber-900': item.type === 'warning',
                'border-sky-200 bg-sky-50 text-sky-900': item.type === 'info',
            }"
        >
            <div class="flex items-start gap-3">
                <p class="min-w-0 flex-1" x-text="item.message"></p>
                <button type="button" @click="dismiss(item)" class="shrink-0 font-bold leading-none opacity-70 hover:opacity-100">×</button>
            </div>
        </div>
    </template>
</div>
