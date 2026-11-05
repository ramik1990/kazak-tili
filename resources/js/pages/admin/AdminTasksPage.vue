<script setup>
import { onMounted, reactive, ref } from 'vue';
import { adminApi } from '@/api';
import { parseError } from '@/api/http';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';
import SkillBadge from '@/components/SkillBadge.vue';

const ui = useUiStore();
const { t } = useI18n();

const tasks = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(true);
const filters = reactive({ skill: 'listening', without_audio: false, page: 1 });
const uploading = ref(null);
const keyDialog = reactive({ open: false, task: null, answers: {} });

const skills = ['reading', 'listening', 'speaking', 'writing', 'literature', 'project'];

async function load() {
    loading.value = true;
    try {
        const { data } = await adminApi.tasks({
            skill: filters.skill || undefined,
            without_audio: filters.without_audio ? 1 : undefined,
            page: filters.page,
            per_page: 50,
        });
        tasks.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        ui.error(parseError(error).message);
    } finally {
        loading.value = false;
    }
}

function reload() {
    filters.page = 1;
    load();
}

async function upload(task, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploading.value = task.id;
    try {
        const { data } = await adminApi.uploadAudio(task.id, file);
        task.audio = data.data.audio;
        ui.success(t('admin.audioUploaded'));
    } catch (error) {
        ui.error(parseError(error).message);
    } finally {
        uploading.value = null;
    }
}

async function removeAudio(task) {
    await adminApi.deleteAudio(task.id);
    task.audio = null;
    ui.success(t('admin.audioRemoved'));
}

function openKey(task) {
    keyDialog.task = task;
    keyDialog.answers = { ...(task.answer_key?.answers ?? {}) };
    keyDialog.open = true;
}

async function saveKey() {
    try {
        const { data } = await adminApi.updateTask(keyDialog.task.id, { answers: keyDialog.answers });
        Object.assign(keyDialog.task, data.data);
        keyDialog.open = false;
        ui.success(t('common.save'));
    } catch (error) {
        ui.error(parseError(error).message);
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="d-flex flex-wrap align-center ga-3 mb-5">
            <h1 class="display-title text-h5">{{ t('nav.tasks') }}</h1>
            <v-chip size="small" variant="tonal">{{ meta.total }}</v-chip>
            <v-spacer />
            <v-select
                v-model="filters.skill"
                :items="skills.map((skill) => ({ title: t('skills.' + skill), value: skill }))"
                :label="t('common.all')"
                clearable
                style="max-width: 220px"
                @update:model-value="reload"
            />
            <v-switch
                v-model="filters.without_audio"
                color="primary"
                hide-details
                density="compact"
                :label="t('admin.withoutAudio')"
                @update:model-value="reload"
            />
        </div>

        <v-skeleton-loader v-if="loading" type="table" />

        <section v-else class="poster poster--violet">
            <span class="poster__badge"><span class="emoji">🧩</span> {{ t('nav.tasks') }}</span>
            <v-table density="comfortable" class="bg-transparent">
                <thead>
                    <tr>
                        <th style="width: 90px">{{ t('admin.lesson') }}</th>
                        <th style="width: 70px">№</th>
                        <th>{{ t('admin.task') }}</th>
                        <th style="width: 140px">{{ t('admin.type') }}</th>
                        <th style="width: 280px">{{ t('admin.audio') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="task in tasks" :key="task.id">
                        <td class="text-medium-emphasis">{{ task.lesson?.number ?? '—' }}</td>
                        <td class="font-weight-bold">{{ task.number }}</td>
                        <td>
                            <div class="text-body-2">{{ task.prompt }}</div>
                            <SkillBadge :skill="task.skill" class="mt-1" />
                        </td>
                        <td><v-chip size="x-small" variant="tonal">{{ task.type }}</v-chip></td>
                        <td>
                            <div v-if="task.skill === 'listening'" class="d-flex align-center ga-2 mb-1">
                                <audio v-if="task.audio" :src="task.audio" controls style="height: 32px; max-width: 170px" />
                                <v-btn v-if="task.audio" icon="mdi-delete-outline" size="x-small" variant="text" color="error" @click="removeAudio(task)" />
                                <label v-else>
                                    <input type="file" accept="audio/*" hidden @change="(event) => upload(task, event)">
                                    <v-btn size="small" variant="tonal" prepend-icon="mdi-upload" :loading="uploading === task.id" tag="span">
                                        {{ t('admin.audio') }}
                                    </v-btn>
                                </label>
                            </div>
                            <v-btn
                                v-if="task.type === 'test'"
                                size="small"
                                variant="text"
                                prepend-icon="mdi-key-outline"
                                :color="task.has_key ? 'success' : 'warning'"
                                @click="openKey(task)"
                            >
                                {{ task.has_key ? t('admin.keyPresent') : t('admin.keyMissing') }}
                            </v-btn>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </section>

        <div v-if="meta.last_page > 1" class="d-flex justify-center mt-4">
            <v-pagination v-model="filters.page" :length="meta.last_page" :total-visible="7" @update:model-value="load" />
        </div>

        <v-dialog v-model="keyDialog.open" max-width="560">
            <v-card v-if="keyDialog.task" class="pa-4" rounded="xl">
                <v-card-title>{{ t('admin.testAnswers') }}</v-card-title>
                <v-card-text style="max-height: 60vh; overflow-y: auto">
                    <div v-for="question in keyDialog.task.questions" :key="question.number" class="mb-4">
                        <div class="text-body-2 mb-1">{{ question.number }}. {{ question.text }}</div>
                        <v-btn-toggle v-model="keyDialog.answers[question.number]" density="compact" variant="outlined" divided rounded="lg">
                            <v-btn v-for="option in question.options" :key="option.label" :value="option.label" size="small">
                                {{ option.label }}
                            </v-btn>
                        </v-btn-toggle>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="keyDialog.open = false">{{ t('common.cancel') }}</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveKey">{{ t('common.save') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
