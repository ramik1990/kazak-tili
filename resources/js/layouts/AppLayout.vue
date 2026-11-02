<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useI18n } from '@/composables/useI18n';
import { useTheme } from 'vuetify';

const auth = useAuthStore();
const router = useRouter();
const theme = useTheme();
const { t, locale, toggle } = useI18n();
const drawer = ref(false);

const links = computed(() => {
    const items = [
        { to: { name: 'textbook' }, title: t('nav.textbook'), icon: 'mdi-book-open-page-variant-outline' },
        { to: { name: 'glossary' }, title: t('nav.glossary'), icon: 'mdi-translate' },
    ];

    if (!auth.isTeacher) {
        items.push({ to: { name: 'progress' }, title: t('nav.progress'), icon: 'mdi-chart-donut' });
    }

    if (auth.isTeacher) {
        items.push(
            { to: { name: 'review' }, title: t('nav.review'), icon: 'mdi-check-decagram-outline' },
            { to: { name: 'journal' }, title: t('nav.journal'), icon: 'mdi-table-account' },
        );
    }

    if (auth.isAdmin) {
        items.push(
            { to: { name: 'admin.tasks' }, title: t('nav.tasks'), icon: 'mdi-playlist-edit' },
            { to: { name: 'admin.users' }, title: t('nav.users'), icon: 'mdi-account-multiple-outline' },
        );
    }

    return items;
});

const isDark = computed(() => theme.global.name.value === 'dark');

function switchTheme() {
    theme.global.name.value = isDark.value ? 'light' : 'dark';
    localStorage.setItem('uchebnik_theme', theme.global.name.value);
}

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}

const stored = localStorage.getItem('uchebnik_theme');
if (stored) theme.global.name.value = stored;
</script>

<template>
    <v-app-bar flat height="68" class="no-print" border>
        <v-app-bar-nav-icon class="d-md-none" @click="drawer = !drawer" />

        <RouterLink :to="{ name: 'textbook' }" class="d-flex align-center ga-3 text-decoration-none ml-3">
            <v-avatar color="primary" size="40" rounded="lg">
                <v-icon icon="mdi-leaf" color="white" />
            </v-avatar>
            <div class="d-none d-sm-block">
                <div class="text-subtitle-1 font-weight-bold text-high-emphasis" style="line-height: 1.1">{{ t('appName') }}</div>
                <div class="text-caption text-medium-emphasis">{{ t('appSubtitle') }}</div>
            </div>
        </RouterLink>

        <v-spacer />

        <div class="d-none d-md-flex ga-1">
            <v-btn v-for="link in links" :key="link.title" :to="link.to" variant="text" :prepend-icon="link.icon">
                {{ link.title }}
            </v-btn>
        </div>

        <v-btn class="ml-1" variant="text" size="small" @click="toggle">
            <span class="font-weight-bold">{{ locale === 'kk' ? 'ҚАЗ' : 'РУС' }}</span>
        </v-btn>

        <v-btn :icon="isDark ? 'mdi-weather-sunny' : 'mdi-weather-night'" variant="text" @click="switchTheme" />

        <v-menu>
            <template #activator="{ props }">
                <v-btn v-bind="props" variant="text" class="text-none" prepend-icon="mdi-account-circle-outline">
                    <span class="d-none d-sm-inline">{{ auth.user?.name }}</span>
                </v-btn>
            </template>
            <v-list density="compact" rounded="lg">
                <v-list-item :to="{ name: 'settings' }" prepend-icon="mdi-cog-outline" :title="t('nav.settings')" />
                <v-divider />
                <v-list-item prepend-icon="mdi-logout" :title="t('nav.logout')" @click="logout" />
            </v-list>
        </v-menu>
    </v-app-bar>

    <v-navigation-drawer v-model="drawer" temporary class="no-print">
        <v-list density="comfortable" nav>
            <v-list-item
                v-for="link in links"
                :key="link.title"
                :to="link.to"
                :prepend-icon="link.icon"
                :title="link.title"
                @click="drawer = false"
            />
        </v-list>
    </v-navigation-drawer>

    <v-main class="app-shell">
        <div class="page pt-6">
            <slot />
        </div>
    </v-main>
</template>
