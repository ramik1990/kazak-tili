<script setup>
import { onMounted, reactive, ref } from 'vue';
import { teacherApi } from '@/api';
import { parseError } from '@/api/http';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';
import SkillBadge from '@/components/SkillBadge.vue';

const ui = useUiStore();
const { t } = useI18n();

const answers = ref([]);
const groups = ref([]);
const loading = ref(true);
const filters = reactive({ group_id: null });
const drafts = reactive({});

async function load() {
    loading.value = true;
    try {
        const { data } = await teacherApi.answers({ group_id: filters.group_id || undefined });
        answers.value = data.data;
        data.data.forEach((answer) => {
            drafts[answer.id] = { score: answer.max_score ?? 1, feedback: '' };
        });
    } catch (error) {
        ui.error(parseError(error).message);
    } finally {
        loading.value = false;
    }
}

async function check(answer) {
    try {
        await teacherApi.check(answer.id, drafts[answer.id]);
        answers.value = answers.value.filter((item) => item.id !== answer.id);
        ui.success(t('task.checked'));
    } catch (error) {
        ui.error(parseError(error).message);
    }
}

onMounted(async () => {
    const { data } = await teacherApi.groups();
    groups.value = data.data;
    load();
});
</script>

<template>
    <div>
        <div class="d-flex flex-wrap align-center ga-3 mb-5">
            <h1 class="display-title text-h5">{{ t('nav.review') }}</h1>
            <v-chip size="small" variant="tonal">{{ answers.length }}</v-chip>
            <v-spacer />
            <v-select
                v-model="filters.group_id"
                :items="groups"
                item-title="name"
                item-value="id"
                :label="t('auth.group')"
                clearable
                style="max-width: 260px"
                @update:model-value="load"
            />
        </div>

        <v-skeleton-loader v-if="loading" type="article@3" />

        <v-card v-else-if="!answers.length" class="pa-10 text-center" border>
            <v-icon icon="mdi-check-all" size="48" class="text-medium-emphasis mb-2" />
            <div class="text-medium-emphasis">{{ t('common.empty') }}</div>
        </v-card>

        <v-card v-for="answer in answers" :key="answer.id" class="pa-5 mb-4" border>
            <div class="d-flex flex-wrap align-center ga-2 mb-2">
                <v-chip size="small" variant="flat" color="primary">{{ answer.user?.name }}</v-chip>
                <SkillBadge v-if="answer.task?.skill" :skill="answer.task.skill" />
                <span class="text-caption text-medium-emphasis">{{ answer.task?.number }}-тапсырма</span>
            </div>

            <div class="text-body-2 font-weight-medium mb-2">{{ answer.task?.prompt }}</div>

            <v-card class="pa-4 mb-3" color="surface-variant">
                <div class="text-overline mb-1">{{ t('task.yourAnswer') }}</div>
                <div v-if="answer.payload?.input?.text" class="read text-body-2">{{ answer.payload.input.text }}</div>
                <pre v-else class="text-body-2" style="white-space: pre-wrap">{{ JSON.stringify(answer.payload?.input, null, 2) }}</pre>
            </v-card>

            <v-alert v-if="answer.task?.answer_key?.text" type="success" variant="tonal" density="compact" class="mb-3">
                {{ t('task.answerKey') }}: {{ answer.task.answer_key.text }}
            </v-alert>

            <v-row dense align="center">
                <v-col cols="12" sm="3">
                    <v-text-field v-model.number="drafts[answer.id].score" type="number" min="0" :label="t('task.score')" />
                </v-col>
                <v-col cols="12" sm="7">
                    <v-text-field v-model="drafts[answer.id].feedback" label="Пікір" />
                </v-col>
                <v-col cols="12" sm="2">
                    <v-btn color="primary" variant="flat" block @click="check(answer)">{{ t('task.check') }}</v-btn>
                </v-col>
            </v-row>
        </v-card>
    </div>
</template>
