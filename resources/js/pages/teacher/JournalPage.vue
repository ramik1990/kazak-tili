<script setup>
import { onMounted, ref } from 'vue';
import { teacherApi } from '@/api';
import { useI18n } from '@/composables/useI18n';
import { percent } from '@/utils/format';

const { t } = useI18n();
const groups = ref([]);
const groupId = ref(null);
const journal = ref(null);
const loading = ref(false);

async function load() {
    if (!groupId.value) return;
    loading.value = true;
    const { data } = await teacherApi.journal(groupId.value);
    journal.value = data.data;
    loading.value = false;
}

function cell(student, lessonId) {
    return student.lessons?.[lessonId] ?? null;
}

onMounted(async () => {
    const { data } = await teacherApi.groups();
    groups.value = data.data;
    groupId.value = groups.value[0]?.id ?? null;
    load();
});
</script>

<template>
    <div>
        <div class="d-flex flex-wrap align-center ga-3 mb-5">
            <h1 class="display-title text-h5">{{ t('nav.journal') }}</h1>
            <v-spacer />
            <v-select
                v-model="groupId"
                :items="groups"
                item-title="name"
                item-value="id"
                :label="t('auth.group')"
                style="max-width: 260px"
                @update:model-value="load"
            />
        </div>

        <v-skeleton-loader v-if="loading" type="table" />

        <v-card v-else-if="journal" class="pa-2" border>
            <div style="overflow-x: auto">
                <table class="doc-table">
                    <thead>
                        <tr>
                            <th style="min-width: 180px">Студент</th>
                            <th v-for="lesson in journal.lessons" :key="lesson.id" class="text-center" :title="lesson.title">
                                {{ lesson.number }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="student in journal.students" :key="student.id">
                            <td class="font-weight-medium">{{ student.name }}</td>
                            <td v-for="lesson in journal.lessons" :key="lesson.id" class="text-center">
                                <v-progress-circular
                                    v-if="cell(student, lesson.id)"
                                    :model-value="percent(cell(student, lesson.id).done_tasks, cell(student, lesson.id).total_tasks)"
                                    :color="cell(student, lesson.id).status === 'completed' ? 'success' : 'warning'"
                                    size="26"
                                    width="3"
                                />
                                <span v-else class="text-disabled">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </v-card>
    </div>
</template>
