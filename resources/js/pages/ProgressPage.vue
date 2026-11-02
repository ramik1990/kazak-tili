<script setup>
import { computed, onMounted, ref } from 'vue';
import { textbookApi } from '@/api';
import { useI18n } from '@/composables/useI18n';
import { percent } from '@/utils/format';

const { t } = useI18n();
const sections = ref([]);
const loading = ref(true);

const lessons = computed(() => sections.value.flatMap((s) => s.lessons.map((l) => ({ ...l, accent: s.accent, roman: s.roman }))));
const totals = computed(() => {
    const done = lessons.value.reduce((sum, l) => sum + (l.progress?.done_tasks ?? 0), 0);
    const total = lessons.value.reduce((sum, l) => sum + (l.progress?.total_tasks ?? l.tasks_count ?? 0), 0);
    const score = lessons.value.reduce((sum, l) => sum + (l.progress?.score ?? 0), 0);
    const max = lessons.value.reduce((sum, l) => sum + (l.progress?.max_score ?? 0), 0);
    return { done, total, score, max, percent: percent(done, total) };
});

onMounted(async () => {
    const { data } = await textbookApi.index();
    sections.value = data.data;
    loading.value = false;
});
</script>

<template>
    <div>
        <h1 class="display-title text-h5 mb-5">{{ t('nav.progress') }}</h1>

        <v-row class="mb-2">
            <v-col cols="12" sm="4">
                <v-card class="pa-5 text-center" color="surface-variant">
                    <div class="text-h3 font-weight-bold">{{ totals.percent }}%</div>
                    <div class="text-caption text-medium-emphasis">орындалды</div>
                </v-card>
            </v-col>
            <v-col cols="6" sm="4">
                <v-card class="pa-5 text-center" border>
                    <div class="text-h4 font-weight-bold">{{ totals.done }}</div>
                    <div class="text-caption text-medium-emphasis">{{ t('lesson.tasks') }}</div>
                </v-card>
            </v-col>
            <v-col cols="6" sm="4">
                <v-card class="pa-5 text-center" border>
                    <div class="text-h4 font-weight-bold">{{ totals.score }}<span class="text-h6 text-medium-emphasis">/{{ totals.max }}</span></div>
                    <div class="text-caption text-medium-emphasis">{{ t('task.score') }}</div>
                </v-card>
            </v-col>
        </v-row>

        <v-skeleton-loader v-if="loading" type="table" />

        <v-card v-else class="pa-2" border>
            <v-table density="comfortable">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>{{ t('lesson.lesson') }}</th>
                        <th class="text-end">{{ t('lesson.tasks') }}</th>
                        <th class="text-end">{{ t('task.score') }}</th>
                        <th style="width: 180px"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="lesson in lessons" :key="lesson.id">
                        <td class="font-weight-bold">{{ lesson.number }}</td>
                        <td>
                            <RouterLink :to="{ name: 'lesson', params: { id: lesson.id } }">{{ lesson.title }}</RouterLink>
                        </td>
                        <td class="text-end">{{ lesson.progress?.done_tasks ?? 0 }} / {{ lesson.progress?.total_tasks ?? lesson.tasks_count }}</td>
                        <td class="text-end">{{ lesson.progress?.score ?? 0 }} / {{ lesson.progress?.max_score ?? 0 }}</td>
                        <td>
                            <v-progress-linear
                                :model-value="percent(lesson.progress?.done_tasks ?? 0, lesson.progress?.total_tasks ?? lesson.tasks_count)"
                                :color="lesson.accent"
                                height="6"
                                rounded
                            />
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </div>
</template>
