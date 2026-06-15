<style>
    .btn-coreti-primary,
    .btn-coreti-secondary,
    .btn-coreti-danger,
    .toolbar-btn,
    .toolbar-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        border-radius: 10px;
        padding: 0 14px;
        font-size: 14px;
        font-weight: 600;
        line-height: 1;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
    }

    .btn-coreti-primary,
    .toolbar-btn-primary {
        border: 1px solid #033151;
        background: #033151;
        color: #ffffff !important;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
    }

    .btn-coreti-primary:hover,
    .toolbar-btn-primary:hover {
        background: #022740;
        border-color: #022740;
    }

    .btn-coreti-secondary,
    .toolbar-btn {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #033151 !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    }

    .btn-coreti-secondary:hover,
    .toolbar-btn:hover {
        background: #f8fafc;
        border-color: #94a3b8;
    }

    .btn-coreti-danger {
        border: 1px solid #b91c1c;
        background: #b91c1c;
        color: #ffffff !important;
        box-shadow: 0 10px 24px rgba(185, 28, 28, 0.12);
    }

    .btn-coreti-danger:hover {
        background: #991b1b;
        border-color: #991b1b;
    }

    .btn-coreti-primary:focus-visible,
    .btn-coreti-secondary:focus-visible,
    .btn-coreti-danger:focus-visible,
    .toolbar-btn:focus-visible,
    .toolbar-btn-primary:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(3, 49, 81, 0.12);
    }

    .btn-coreti-primary:disabled,
    .btn-coreti-secondary:disabled,
    .btn-coreti-danger:disabled,
    .toolbar-btn:disabled,
    .toolbar-btn-primary:disabled,
    .btn-coreti-disabled {
        background: #e5e7eb !important;
        border-color: #cbd5e1 !important;
        color: #475569 !important;
        cursor: not-allowed !important;
        opacity: 1 !important;
        box-shadow: none !important;
    }
</style>
