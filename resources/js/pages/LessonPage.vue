<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { textbookApi } from '@/api';
import { parseError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';
import { percent } from '@/utils/format';
import LessonBlock from '@/components/LessonBlock.vue';
import TaskCard from '@/components/TaskCard.vue';

const props = defineProps({ id: { type: String, required: true } });

const ui = useUiStore();
const auth = useAuthStore();
const { t } = useI18n();
const lesson = ref(null);
const tab = ref('lesson');

const accent = computed(() => lesson.value?.section?.accent || '#1B5E4A');
const progress = computed(() => lesson.value?.progress);

const stream = computed(() => {
    if (!lesson.value) return [];

    const tasks = [...lesson.value.tasks].sort((a, b) => a.position - b.position);
    const blocks = [...lesson.value.blocks].sort((a, b) => a.position - b.position);
    const merged = [
        ...blocks.map((block) => ({ kind: 'block', position: block.position, data: block })),
        ...tasks.map((task) => ({ kind: 'task', position: task.position, data: task })),
    ];

    return merged.sort((a, b) => a.position - b.position);
});

async function load() {
    lesson.value = null;
    try {
        const { data } = await textbookApi.lesson(props.id);
        lesson.value = data.data;
    } catch (error) {
        ui.error(parseError(error).message);
    }
}

function onSaved() {
    textbookApi.lesson(props.id).then(({ data }) => {
        lesson.value.progress = data.data.progress;
    });
}

function print() {
    const previous = document.title;
    document.title = `${lesson.value.number}-сабақ. ${lesson.value.title}`;
    window.print();
    document.title = previous;
}

watch(() => props.id, load);
onMounted(load);
</script>

<template>
    <v-skeleton-loader v-if="!lesson" type="image, article, paragraph@3" />

    <div v-else class="page-narrow mx-auto">
        <div class="d-flex flex-wrap align-center ga-2 mb-3 no-print">
            <v-btn variant="text" prepend-icon="mdi-arrow-left" :to="{ name: 'section', params: { id: lesson.section.id } }">
                {{ lesson.section.title }}
            </v-btn>
            <v-spacer />
            <v-btn variant="tonal" prepend-icon="mdi-printer" @click="print">{{ t('lesson.print') }}</v-btn>
        </div>

        <v-alert
            v-if="!auth.isAuthenticated"
            type="info"
            variant="tonal"
            icon="mdi-school-outline"
            class="mb-5 no-print"
        >
            <div class="d-flex flex-wrap align-center ga-3">
                <span class="flex-grow-1">{{ t('lesson.guestNotice') }}</span>
                <v-btn color="primary" variant="flat" size="small" :to="{ name: 'login' }">{{ t('auth.signIn') }}</v-btn>
                <v-btn variant="text" size="small" :to="{ name: 'register' }">{{ t('auth.signUp') }}</v-btn>
            </div>
        </v-alert>

        <div class="print-sheet">
            <div class="cover mb-6">
                <img v-if="lesson.cover || lesson.section.cover" :src="lesson.cover || lesson.section.cover" alt="">
                <div class="cover__body">
                    <div class="text-caption text-uppercase" style="letter-spacing: 0.18em; opacity: 0.85">
                        {{ lesson.section.roman }} {{ t('lesson.section') }}
                    </div>
                    <h1 class="display-title text-h5 text-md-h4 mt-1">{{ lesson.number }}-сабақ. {{ lesson.title }}</h1>
                </div>
            </div>

            <v-row class="mb-2">
                <v-col cols="12" :md="lesson.grammar_topic || lesson.methods.length || progress ? 7 : 12">
                    <v-card class="pa-5 h-100" color="surface-variant">
                        <div class="text-overline">{{ t('lesson.goal') }}</div>
                        <p class="read text-body-2 mb-4">{{ lesson.goal }}</p>

                        <template v-if="lesson.outcomes.length">
                            <div class="text-overline">{{ t('lesson.outcomes') }}</div>
                            <ul class="ml-4 text-body-2">
                                <li v-for="(item, i) in lesson.outcomes" :key="i">{{ item }}</li>
                            </ul>
                        </template>
                    </v-card>
                </v-col>
                <v-col v-if="lesson.grammar_topic || lesson.methods.length || progress" cols="12" md="5">
                    <v-card class="pa-5 h-100" border>
                        <div v-if="lesson.grammar_topic" class="mb-3">
                            <div class="text-overline">{{ t('lesson.grammar') }}</div>
                            <div class="text-body-2 font-weight-medium">{{ lesson.grammar_topic }}</div>
                        </div>
                        <div v-if="lesson.methods.length" class="mb-3">
                            <div class="text-overline">{{ t('lesson.methods') }}</div>
                            <v-chip v-for="(m, i) in lesson.methods" :key="i" size="x-small" variant="tonal" class="mr-1 mb-1">{{ m }}</v-chip>
                        </div>
                        <div v-if="progress" class="no-print">
                            <div class="text-overline">{{ t('nav.progress') }}</div>
                            <v-progress-linear
                                :model-value="percent(progress.done_tasks, progress.total_tasks)"
                                :color="accent"
                                height="8"
                                rounded
                            />
                            <div class="text-caption text-medium-emphasis mt-1">
                                {{ progress.done_tasks }} {{ t('common.of') }} {{ progress.total_tasks }}
                            </div>
                        </div>
                    </v-card>
                </v-col>
            </v-row>

            <v-card v-if="lesson.support_words.length" class="pa-5 my-5" border>
                <div class="text-overline mb-2">{{ t('lesson.supportWords') }}</div>
                <v-chip v-for="(word, i) in lesson.support_words" :key="i" class="mr-1 mb-1" variant="tonal" size="small">
                    {{ word.kk }} — {{ word.ru }}
                </v-chip>
            </v-card>

            <template v-for="(entry, index) in stream" :key="index">
                <LessonBlock v-if="entry.kind === 'block'" :block="entry.data" />
                <TaskCard v-else :task="entry.data" @saved="onSaved" />
            </template>

            <template v-if="lesson.terms.length">
                <div class="d-flex align-center ga-3 mt-8 mb-4">
                    <h2 class="text-h6 font-weight-bold">{{ t('lesson.terms') }}</h2>
                    <div class="leaf-divider flex-grow-1" />
                </div>
                <v-card class="pa-4" border>
                    <table class="vocab-table">
                        <tbody>
                            <tr v-for="term in lesson.terms" :key="term.id">
                                <td>{{ term.kk }}</td>
                                <td>
                                    <div>{{ term.ru }}</div>
                                    <div class="text-caption text-medium-emphasis">{{ term.definition }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </v-card>
            </template>
        </div>
    </div>
</template>
