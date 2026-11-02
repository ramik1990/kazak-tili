import { defineStore } from 'pinia';
import { messages } from '@/i18n/messages';

const KEY = 'uchebnik_locale';

export const useLocaleStore = defineStore('locale', {
    state: () => ({ locale: localStorage.getItem(KEY) || 'kk' }),

    getters: {
        messages: (state) => messages[state.locale] ?? messages.kk,
        isKazakh: (state) => state.locale === 'kk',
    },

    actions: {
        set(locale) {
            this.locale = messages[locale] ? locale : 'kk';
            localStorage.setItem(KEY, this.locale);
            document.documentElement.lang = this.locale;
        },

        toggle() {
            this.set(this.locale === 'kk' ? 'ru' : 'kk');
        },

        t(path) {
            return path.split('.').reduce((acc, key) => acc?.[key], this.messages) ?? path;
        },
    },
});
