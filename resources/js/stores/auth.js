import { defineStore } from 'pinia';
import { authApi } from '@/api';
import { tokenStorage } from '@/api/http';
import { useLocaleStore } from '@/stores/locale';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: tokenStorage.get(),
        user: null,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token),
        isTeacher: (state) => ['teacher', 'admin'].includes(state.user?.role),
        isAdmin: (state) => state.user?.role === 'admin',
        home() {
            return this.isTeacher ? { name: 'review' } : { name: 'textbook' };
        },
    },

    actions: {
        setSession(token, user) {
            this.token = token;
            this.user = user;
            tokenStorage.set(token);
            if (user?.locale) useLocaleStore().set(user.locale);
        },

        clearSession() {
            this.token = null;
            this.user = null;
            tokenStorage.clear();
        },

        async login(payload) {
            const { data } = await authApi.login(payload);
            this.setSession(data.token, data.user);
        },

        async register(payload) {
            const { data } = await authApi.register(payload);
            this.setSession(data.token, data.user);
        },

        async fetchUser() {
            const { data } = await authApi.me();
            this.user = data.data;
            if (this.user?.locale) useLocaleStore().set(this.user.locale);
        },

        async logout() {
            try {
                await authApi.logout();
            } finally {
                this.clearSession();
            }
        },
    },
});
