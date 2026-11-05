<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { textbookApi } from '@/api';
import { parseError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';
import { percent } from '@/utils/format';
import { TONES } from '@/utils/skills';
import TaskCard from '@/components/TaskCard.vue';

const props = defineProps({ id: { type: String, required: true } });

const ui = useUiStore();
const auth = useAuthStore();
const { t } = useI18n();
const section = ref(null);

const tone = computed(() => TONES[((section.value?.number ?? 1) - 1) % TONES.length]);

async function load() {
    section.value = null;
    try {
        const { data } = await textbookApi.section(props.id);
        section.value = data.data;
    } catch (error) {
        ui.error(parseError(error).message);
    }
}

watch(() => props.id, load);
onMounted(load);
</script>

<template>
    <v-skeleton-loader v-if="!section" type="image, article, list-item-two-line@4" />

    <div v-else>
        <v-btn variant="text" prepend-icon="mdi-arrow-left" :to="{ name: 'textbook' }" class="mb-3">
            {{ t('nav.textbook') }}
        </v-btn>

        <div :class="['lesson-hero', 'mb-6', `poster--${tone}`]">
            <img v-if="section.cover" :src="section.cover" alt="">
            <div class="lesson-hero__body">
                <span class="ribbon">{{ section.roman }} {{ t('lesson.section') }} · {{ section.lessons_range }}-сабақтар</span>
                <h1 class="display-title text-h4 mt-2">{{ section.title }}</h1>
            </div>
        </div>

        <v-row>
            <v-col cols="12" md="7">
                <section :class="['poster', `poster--${tone}`]">
                    <span class="poster__badge"><span class="emoji">📘</span> Сабақтар</span>

                    <RouterLink
                        v-for="lesson in section.lessons"
                        :key="lesson.id"
                        :to="{ name: 'lesson', params: { id: lesson.id } }"
                        class="text-decoration-none"
                    >
                        <div :class="['lesson-row', 'pa-4', 'mb-3', 'd-flex', 'align-center', 'ga-4', `poster--${tone}`]">
                            <v-avatar size="44" class="font-weight-bold text-white" :style="{ background: 'var(--pc)' }">
                                {{ lesson.number }}
                            </v-avatar>
                            <div class="flex-grow-1">
                                <div class="text-subtitle-1 font-weight-bold text-high-emphasis">{{ lesson.title }}</div>
                                <div class="text-caption text-medium-emphasis">
                                    {{ lesson.tasks_count }} {{ t('lesson.tasks').toLowerCase() }} · {{ lesson.terms_count }} {{ t('lesson.terms').toLowerCase() }}
                                </div>
                                <v-progress-linear
                                    v-if="auth.isAuthenticated && lesson.progress"
                                    :model-value="percent(lesson.progress.done_tasks, lesson.progress.total_tasks)"
                                    color="success"
                                    height="6"
                                    rounded
                                    class="mt-2"
                                />
                            </div>
                            <v-icon icon="mdi-chevron-right" class="text-medium-emphasis" />
                        </div>
                    </RouterLink>
                </section>
            </v-col>

            <v-col cols="12" md="5">
                <section class="poster poster--violet">
                    <span class="poster__badge"><span class="emoji">🎯</span> Бөлімнің мақсаты</span>
                    <p class="read mb-0">{{ section.goal }}</p>
                </section>

                <section v-if="section.objectives.length" class="poster poster--amber">
                    <span class="poster__badge"><span class="emoji">📝</span> Бөлімнің міндеттері</span>
                    <ol class="num-list">
                        <li v-for="(item, i) in section.objectives" :key="i">{{ item }}</li>
                    </ol>
                </section>

                <section v-if="section.outcomes.length" class="poster poster--green">
                    <span class="poster__badge"><span class="emoji">✅</span> Күтілетін нәтижелер</span>
                    <ol class="num-list">
                        <li v-for="(item, i) in section.outcomes" :key="i">{{ item }}</li>
                    </ol>
                </section>
            </v-col>
        </v-row>

        <template v-if="section.tasks?.length">
            <h2 class="text-h6 mt-8 mb-2">Бөлім бойынша қорытынды</h2>
            <TaskCard v-for="task in section.tasks" :key="task.id" :task="task" />
        </template>
    </div>
</template>
