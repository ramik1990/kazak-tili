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
            <v-chip size="small" variant="tonal">{{ terms.length }}</v-chip>
            <v-spacer />
            <v-btn-toggle v-model="mode" mandatory density="comfortable" variant="outlined" divided rounded="lg">
                <v-btn value="list" prepend-icon="mdi-format-list-bulleted" size="small">Тізім</v-btn>
                <v-btn value="cards" prepend-icon="mdi-cards-outline" size="small">Карточка</v-btn>
            </v-btn-toggle>
        </div>

        <v-text-field v-model="query" :label="t('common.search')" prepend-inner-icon="mdi-magnify" clearable class="mb-5" />

        <v-skeleton-loader v-if="loading" type="table" />

        <v-card v-else-if="mode === 'cards' && card" class="pa-10 text-center" border @click="flipped = !flipped">
            <div class="text-overline mb-3">{{ cardIndex + 1 }} / {{ filtered.length }}</div>
            <div class="display-title text-h4 mb-3">{{ flipped ? card.ru : card.kk }}</div>
            <div v-if="flipped" class="text-body-2 text-medium-emphasis">{{ card.definition }}</div>
            <div v-else class="text-caption text-medium-emphasis">Аударманы көру үшін басыңыз</div>
            <v-btn class="mt-6" color="primary" variant="flat" @click.stop="next">Келесі</v-btn>
        </v-card>

        <v-card v-else class="pa-2" border>
            <v-table density="comfortable">
                <thead>
                    <tr>
                        <th>Қазақша</th>
                        <th>Орысша</th>
                        <th>Анықтамасы</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="term in filtered" :key="term.id">
                        <td class="font-weight-bold">{{ term.kk }}</td>
                        <td>{{ term.ru }}</td>
                        <td class="text-medium-emphasis">{{ term.definition }}</td>
                    </tr>
                </tbody>
            </v-table>
        </v-card>
    </div>
</template>
