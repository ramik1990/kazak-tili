<script setup>
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { iconOf, toneOf } from '@/utils/skills';

const props = defineProps({ skill: { type: String, required: true } });
const { t } = useI18n();

const tone = computed(() => toneOf(props.skill));
const style = computed(() => ({
    background: `var(--${tone.value}-soft)`,
    color: `var(--${tone.value}-ink)`,
}));
</script>

<template>
    <span class="skill-badge" :style="style">
        <span>{{ iconOf(skill) }}</span>
        <span>{{ t(`skills.${skill}`) }}</span>
    </span>
</template>
