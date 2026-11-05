<script setup>
import { computed, onMounted, ref } from 'vue';
import { textbookApi } from '@/api';
import { parseError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';
import { percent } from '@/utils/format';
import { TONES } from '@/utils/skills';

const ui = useUiStore();
const auth = useAuthStore();
const { t } = useI18n();

const sections = ref([]);
const loading = ref(true);
const query = ref('');
const results = ref(null);

const hero = computed(() => sections.value[0]?.cover ?? null);

const totals = computed(() => {
    const lessons = sections.value.flatMap((section) => section.lessons);
    const done = lessons.filter((lesson) => lesson.progress?.status === 'completed').length;
    return { lessons: lessons.length, done, percent: percent(done, lessons.length) };
});

async function load() {
    try {
        const { data } = await textbookApi.index();
        sections.value = data.data;
    } catch (error) {
        ui.error(parseError(error).message);
    } finally {
        loading.value = false;
    }
}

let timer = null;

function search() {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        if (query.value.trim().length < 2) {
            results.value = null;
            return;
        }
        const { data } = await textbookApi.search(query.value);
        results.value = data.data;
    }, 350);
}

onMounted(load);
</script>

<template>
    <div>
        <div class="lesson-hero poster--blue mb-8">
            <img v-if="hero" :src="hero" alt="">
            <div class="lesson-hero__body">
                <span class="ribbon">А. Мухтанова · оқу құралы</span>
                <h1 class="display-title text-h4 text-md-h3 mt-2">{{ t('appName') }}</h1>
                <div class="text-subtitle-1 mt-1" style="opacity: 0.9">{{ t('appSubtitle') }}</div>

                <div class="d-flex flex-wrap align-center ga-4 mt-4">
                    <v-chip color="white" variant="flat" size="small" class="font-weight-bold text-primary">
                        {{ sections.length }} {{ t('lesson.section') }}
                    </v-chip>
                    <v-chip color="white" variant="flat" size="small" class="font-weight-bold text-primary">
                        {{ totals.lessons }} {{ t('lesson.lesson') }}
                    </v-chip>
                    <div v-if="auth.isAuthenticated" class="flex-grow-1" style="max-width: 320px">
                        <v-progress-linear :model-value="totals.percent" color="white" bg-color="rgba(255,255,255,0.25)" height="8" rounded />
                        <div class="text-caption mt-1" style="opacity: 0.9">
                            {{ totals.done }} {{ t('common.of') }} {{ totals.lessons }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <v-text-field
            v-model="query"
            :label="t('common.search')"
            prepend-inner-icon="mdi-magnify"
            clearable
            class="mb-6"
            @update:model-value="search"
        />

        <v-card v-if="results" class="mb-6 pa-4" border>
            <div v-if="!results.lessons.length && !results.terms.length && !results.blocks.length" class="text-medium-emphasis">
                {{ t('common.empty') }}
            </div>
            <template v-else>
                <div v-if="results.lessons.length" class="mb-3">
                    <div class="text-overline">{{ t('nav.textbook') }}</div>
                    <RouterLink
                        v-for="lesson in results.lessons"
                        :key="lesson.id"
                        :to="{ name: 'lesson', params: { id: lesson.id } }"
                        class="d-block py-1"
                    >
                        {{ lesson.number }}-сабақ. {{ lesson.title }}
                    </RouterLink>
                </div>
                <div v-if="results.terms.length" class="mb-3">
                    <div class="text-overline">{{ t('nav.glossary') }}</div>
                    <v-chip v-for="term in results.terms" :key="term.id" size="small" class="mr-1 mb-1" variant="tonal">
                        {{ term.kk }} — {{ term.ru }}
                    </v-chip>
                </div>
                <div v-if="results.blocks.length">
                    <div class="text-overline">Мәтіндер</div>
                    <RouterLink
                        v-for="(block, i) in results.blocks"
                        :key="i"
                        :to="{ name: 'lesson', params: { id: block.lesson_id } }"
                        class="d-block py-1 text-body-2"
                    >
                        <span class="text-medium-emphasis">{{ block.lesson_number }}-сабақ:</span> {{ block.excerpt }}…
                    </RouterLink>
                </div>
            </template>
        </v-card>

        <v-row v-if="loading">
            <v-col v-for="n in 4" :key="n" cols="12" md="6">
                <v-skeleton-loader type="image, article" />
            </v-col>
        </v-row>

        <v-row v-else>
            <v-col v-for="section in sections" :key="section.id" cols="12" md="6">
                <RouterLink :to="{ name: 'section', params: { id: section.id } }" class="text-decoration-none">
                <div :class="['section-card', 'h-100', `poster--${TONES[(section.number - 1) % TONES.length]}`]">
                    <v-img v-if="section.cover" :src="section.cover" height="150" cover />
                    <div class="pa-5">
                        <div class="d-flex align-center ga-2 mb-1">
                            <span class="poster__badge" style="position: static; border-width: 2px">{{ section.roman }} бөлім</span>
                            <span class="text-caption text-medium-emphasis">{{ section.lessons_range }}-сабақтар</span>
                        </div>
                        <h3 class="text-h6 font-weight-bold mb-2">{{ section.title }}</h3>
                        <p class="text-body-2 text-medium-emphasis" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden">
                            {{ section.goal }}
                        </p>
                        <div v-if="auth.isAuthenticated" class="d-flex align-center ga-2 mt-3">
                            <v-progress-linear
                                :model-value="percent(section.lessons.filter((l) => l.progress?.status === 'completed').length, section.lessons.length)"
                                color="success"
                                height="8"
                                rounded
                            />
                            <span class="text-caption text-medium-emphasis text-no-wrap">
                                {{ section.lessons.filter((l) => l.progress?.status === 'completed').length }}/{{ section.lessons.length }}
                            </span>
                        </div>
                    </div>
                </div>
                </RouterLink>
            </v-col>
        </v-row>
    </div>
</template>
