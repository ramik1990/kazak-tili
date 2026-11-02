<script setup>
import { onMounted, ref, watch } from 'vue';
import { textbookApi } from '@/api';
import { parseError } from '@/api/http';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';
import { percent } from '@/utils/format';
import TaskCard from '@/components/TaskCard.vue';

const props = defineProps({ id: { type: String, required: true } });

const ui = useUiStore();
const { t } = useI18n();
const section = ref(null);

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

        <div class="cover mb-6">
            <img v-if="section.cover" :src="section.cover" alt="">
            <div class="cover__body">
                <div class="text-caption text-uppercase" style="letter-spacing: 0.18em; opacity: 0.85">
                    {{ section.roman }} {{ t('lesson.section') }} · {{ section.lessons_range }}-сабақтар
                </div>
                <h1 class="display-title text-h4 mt-1">{{ section.title }}</h1>
            </div>
        </div>

        <v-row>
            <v-col cols="12" md="7">
                <h2 class="text-h6 font-weight-bold mb-3">{{ t('lesson.tasks') }} · {{ t('lesson.lesson') }}</h2>

                <RouterLink
                    v-for="lesson in section.lessons"
                    :key="lesson.id"
                    :to="{ name: 'lesson', params: { id: lesson.id } }"
                    class="text-decoration-none"
                >
                    <div class="lesson-row pa-4 mb-3 d-flex align-center ga-4">
                        <v-avatar :color="section.accent" size="44" class="font-weight-bold text-white">
                            {{ lesson.number }}
                        </v-avatar>
                        <div class="flex-grow-1">
                            <div class="text-subtitle-1 font-weight-bold text-high-emphasis">{{ lesson.title }}</div>
                            <div class="text-caption text-medium-emphasis">
                                {{ lesson.tasks_count }} {{ t('lesson.tasks').toLowerCase() }} · {{ lesson.terms_count }} {{ t('lesson.terms').toLowerCase() }}
                            </div>
                            <v-progress-linear
                                v-if="lesson.progress"
                                :model-value="percent(lesson.progress.done_tasks, lesson.progress.total_tasks)"
                                :color="section.accent"
                                height="5"
                                rounded
                                class="mt-2"
                            />
                        </div>
                        <v-icon icon="mdi-chevron-right" class="text-medium-emphasis" />
                    </div>
                </RouterLink>
            </v-col>

            <v-col cols="12" md="5">
                <v-card class="pa-5 mb-4" color="surface-variant">
                    <div class="text-overline">Бөлімнің мақсаты</div>
                    <p class="read text-body-2">{{ section.goal }}</p>
                </v-card>

                <v-card v-if="section.objectives.length" class="pa-5 mb-4" border>
                    <div class="text-overline mb-2">Бөлімнің міндеттері</div>
                    <ul class="ml-4 text-body-2">
                        <li v-for="(item, i) in section.objectives" :key="i" class="mb-1">{{ item }}</li>
                    </ul>
                </v-card>

                <v-card v-if="section.outcomes.length" class="pa-5" border>
                    <div class="text-overline mb-2">Күтілетін оқу нәтижелері</div>
                    <ul class="ml-4 text-body-2">
                        <li v-for="(item, i) in section.outcomes" :key="i" class="mb-1">{{ item }}</li>
                    </ul>
                </v-card>
            </v-col>
        </v-row>

        <template v-if="section.tasks?.length">
            <div class="d-flex align-center ga-3 mt-8 mb-4">
                <h2 class="text-h6 font-weight-bold">Бөлім бойынша қорытынды</h2>
                <div class="leaf-divider flex-grow-1" />
            </div>
            <TaskCard v-for="task in section.tasks" :key="task.id" :task="task" />
        </template>
    </div>
</template>
