<script setup>
import { computed } from 'vue';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();
const state = computed(() => ui.confirmState);
</script>

<template>
    <v-dialog :model-value="Boolean(state)" max-width="440" persistent>
        <v-card v-if="state" class="pa-2" rounded="xl">
            <v-card-title class="text-h6">{{ state.title }}</v-card-title>
            <v-card-text v-if="state.text">{{ state.text }}</v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="ui.resolveConfirm(false)">{{ state.cancelText || 'Болдырмау' }}</v-btn>
                <v-btn color="primary" variant="flat" @click="ui.resolveConfirm(true)">{{ state.confirmText || 'Иә' }}</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
