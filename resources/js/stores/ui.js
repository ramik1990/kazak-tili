import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
    state: () => ({
        toasts: [],
        confirmState: null,
    }),

    actions: {
        notify(text, color = 'success') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, text, color });
            setTimeout(() => this.dismiss(id), 4500);
        },

        success(text) {
            this.notify(text, 'success');
        },

        error(text) {
            this.notify(text, 'error');
        },

        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },

        confirm(options) {
            return new Promise((resolve) => {
                this.confirmState = { ...options, resolve };
            });
        },

        resolveConfirm(value) {
            this.confirmState?.resolve(value);
            this.confirmState = null;
        },
    },
});
