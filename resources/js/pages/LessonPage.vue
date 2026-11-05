<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { textbookApi } from '@/api';
import { parseError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';
import { percent } from '@/utils/format';
import { TONES } from '@/utils/skills';
import LessonBlock from '@/components/LessonBlock.vue';
import TaskCard from '@/components/TaskCard.vue';

const props = defineProps({ id: { type: String, required: true } });

const ui = useUiStore();
const auth = useAuthStore();
const { t } = useI18n();
const lesson = ref(null);
const tab = ref('lesson');

const tone = computed(() => TONES[((lesson.value?.section?.number ?? 1) - 1) % TONES.length]);
const progress = computed(() => lesson.value?.progress);

const stream = computed(() => {
    if (!lesson.value) return [];

    const tasks = [...lesson.value.tasks].sort((a, b) => a.position - b.position);
    const blocks = [...lesson.value.blocks].sort((a, b) => a.position - b.position);
    const merged = [
        ...blocks.map((block) => ({ kind: 'block', position: block.position, data: block })),
        ...tasks.map((task) => ({ kind: 'task', position: task.position, data: task })),
    ].sort((a, b) => a.position - b.position);

    const out = [];

    for (const entry of merged) {
        const last = out[out.length - 1];
        const isText = entry.kind === 'block' && entry.data.type === 'text';

        if (isText && last?.kind === 'block' && last.data.type === 'text') {
            last.data.paragraphs.push(entry.data.text);
            continue;
        }

        out.push(isText
            ? { kind: 'block', position: entry.position, data: { type: 'text', paragraphs: [entry.data.text] } }
            : entry);
    }

    return out;
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
            <div :class="['lesson-hero', 'mb-5', `poster--${tone}`]">
                <img v-if="lesson.cover || lesson.section.cover" :src="lesson.cover || lesson.section.cover" alt="">
                <div class="lesson-hero__body">
                    <span class="ribbon">{{ lesson.section.roman }} {{ t('lesson.section') }}</span>
                    <h1 class="display-title text-h5 text-md-h4 mt-2">{{ lesson.number }}-сабақ. {{ lesson.title }}</h1>
                </div>
            </div>

            <section class="poster poster--violet">
                <span class="poster__badge"><span class="emoji">🎯</span> {{ t('lesson.goal') }}</span>
                <p class="read mb-4">{{ lesson.goal }}</p>

                <template v-if="lesson.outcomes.length">
                    <div class="font-weight-bold mb-1">{{ t('lesson.outcomes') }}</div>
                    <ol class="num-list">
                        <li v-for="(item, i) in lesson.outcomes" :key="i">{{ item }}</li>
                    </ol>
                </template>
            </section>

            <section v-if="lesson.grammar_topic || lesson.methods.length || progress" class="poster poster--pink">
                <span class="poster__badge"><span class="emoji">🧩</span> {{ t('lesson.grammar') }}</span>

                <div v-if="lesson.grammar_topic" class="text-body-1 font-weight-bold mb-3">{{ lesson.grammar_topic }}</div>

                <div v-if="lesson.methods.length" class="mb-3">
                    <v-chip v-for="(m, i) in lesson.methods" :key="i" size="small" variant="tonal" class="mr-1 mb-1">{{ m }}</v-chip>
                </div>

                <div v-if="progress" class="no-print">
                    <v-progress-linear
                        :model-value="percent(progress.done_tasks, progress.total_tasks)"
                        color="success"
                        height="10"
                        rounded
                    />
                    <div class="text-caption text-medium-emphasis mt-1">
                        {{ progress.done_tasks }} {{ t('common.of') }} {{ progress.total_tasks }}
                    </div>
                </div>
            </section>

            <section v-if="lesson.support_words.length" class="poster poster--green">
                <span class="poster__badge"><span class="emoji">🔑</span> {{ t('lesson.supportWords') }}</span>
                <table class="vocab-table">
                    <tbody>
                        <tr v-for="(word, i) in lesson.support_words" :key="i">
                            <td>{{ word.kk }}</td>
                            <td>{{ word.ru }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <template v-for="(entry, index) in stream" :key="index">
                <LessonBlock v-if="entry.kind === 'block'" :block="entry.data" />
                <TaskCard v-else :task="entry.data" @saved="onSaved" />
            </template>

            <section v-if="lesson.terms.length" class="poster poster--blue">
                <span class="poster__badge"><span class="emoji">📚</span> {{ t('lesson.terms') }}</span>
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
            </section>
        </div>
    </div>
</template>
