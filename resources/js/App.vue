<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';
import ToastHost from '@/components/ToastHost.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';

const route = useRoute();
const bare = computed(() => Boolean(route.meta.guest));
</script>

<template>
    <v-app>
        <RouterView v-if="bare" />
        <AppLayout v-else>
            <RouterView />
        </AppLayout>
        <ToastHost />
        <ConfirmDialog />
    </v-app>
</template>
