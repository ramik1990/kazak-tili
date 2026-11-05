<script setup>
import { computed } from 'vue';
import { highlight } from '@/utils/highlight';

const props = defineProps({
    text: { type: String, default: '' },
    terms: { type: Array, default: () => [] },
});

const chunks = computed(() => highlight(props.text, props.terms));
</script>

<template>
    <span>
        <template v-for="(chunk, i) in chunks" :key="i">
            <mark v-if="chunk.term" :class="['hl', `hl--${chunk.term.kind}`]" :title="chunk.term.ru">{{ chunk.text }}</mark>
            <template v-else>{{ chunk.text }}</template>
        </template>
    </span>
</template>
