<script setup>
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';
import { SKILLS } from '@/utils/skills';

const props = defineProps({ skill: { type: String, required: true } });
const { t } = useI18n();

const meta = computed(() => SKILLS[props.skill] ?? SKILLS.reading);
</script>

<template>
    <span class="skill-badge" :style="{ background: meta.soft, color: meta.color }">
        <span>{{ meta.icon }}</span>
        <span>{{ t(`skills.${skill}`) }}</span>
    </span>
</template>
