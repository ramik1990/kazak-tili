<script setup>
import { computed, onMounted, ref } from 'vue';
import { textbookApi } from '@/api';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();
const terms = ref([]);
const query = ref('');
const loading = ref(true);
const mode = ref('list');
const cardIndex = ref(0);
const flipped = ref(false);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return terms.value;
    return terms.value.filter((term) => `${term.kk} ${term.ru} ${term.definition}`.toLowerCase().includes(q));
});

const card = computed(() => filtered.value[cardIndex.value % Math.max(filtered.value.length, 1)]);

async function load() {
    const { data } = await textbookApi.glossary();
    terms.value = data.data;
    loading.value = false;
}

function next() {
    flipped.value = false;
    cardIndex.value = (cardIndex.value + 1) % Math.max(filtered.value.length, 1);
}

onMounted(load);
</script>

<template>
    <div>
        <div class="d-flex flex-wrap align-center ga-3 mb-5">
            <h1 class="display-title text-h5">{{ t('nav.glossary') }}</h1>
            <v-chip size="small" color="secondary" variant="flat">{{ terms.length }}</v-chip>
            <v-spacer />
            <v-btn-toggle v-model="mode" mandatory density="comfortable" variant="outlined" divided rounded="pill">
                <v-btn value="list" prepend-icon="mdi-format-list-bulleted" size="small">{{ t('glossary.list') }}</v-btn>
                <v-btn value="cards" prepend-icon="mdi-cards-outline" size="small">{{ t('glossary.cards') }}</v-btn>
            </v-btn-toggle>
        </div>

        <v-text-field v-model="query" :label="t('common.search')" prepend-inner-icon="mdi-magnify" clearable class="mb-5" />

        <v-skeleton-loader v-if="loading" type="table" />

        <section
            v-else-if="mode === 'cards' && card"
            class="poster poster--violet text-center"
            style="cursor: pointer"
            @click="flipped = !flipped"
        >
            <span class="poster__badge"><span class="emoji">🗂️</span> {{ cardIndex + 1 }} / {{ filtered.length }}</span>
            <div class="display-title text-h4 my-6">{{ flipped ? card.ru : card.kk }}</div>
            <div v-if="flipped && card.definition" class="read text-body-2 mb-4">{{ card.definition }}</div>
            <div v-else-if="!flipped" class="text-caption text-medium-emphasis mb-4">{{ t('glossary.flip') }}</div>
            <v-btn color="primary" variant="flat" @click.stop="next">{{ t('glossary.next') }}</v-btn>
        </section>

        <section v-else class="poster poster--green">
            <span class="poster__badge"><span class="emoji">📚</span> {{ t('nav.glossary') }}</span>
            <div style="overflow-x: auto">
                <table class="doc-table">
                    <thead>
                        <tr>
                            <th>{{ t('glossary.kazakh') }}</th>
                            <th>{{ t('glossary.russian') }}</th>
                            <th>{{ t('glossary.definition') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="term in filtered" :key="term.id">
                            <td class="font-weight-bold">{{ term.kk }}</td>
                            <td>{{ term.ru }}</td>
                            <td class="text-medium-emphasis">{{ term.definition }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
