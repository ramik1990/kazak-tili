<script setup>
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();
</script>

<template>
    <div class="toast-host">
        <v-snackbar
            v-for="(toast, index) in ui.toasts"
            :key="toast.id"
            :model-value="true"
            :color="toast.color"
            location="bottom right"
            :style="{ marginBottom: `${index * 64}px` }"
            timeout="-1"
            rounded="lg"
        >
            {{ toast.text }}
            <template #actions>
                <v-btn icon="mdi-close" variant="text" size="small" @click="ui.dismiss(toast.id)" />
            </template>
        </v-snackbar>
    </div>
</template>
