<script setup>
import { computed } from 'vue';

const props = defineProps({ block: { type: Object, required: true } });

const rows = computed(() => props.block.payload?.rows ?? []);
const words = computed(() => props.block.payload?.words ?? []);
const files = computed(() => props.block.payload?.files ?? []);
const paragraphs = computed(() => props.block.paragraphs ?? []);
</script>

<template>
    <div v-if="block.type === 'heading'" class="d-flex align-center ga-3 mt-8 mb-1">
        <h3 class="text-h6">{{ block.text }}</h3>
        <div class="flex-grow-1" style="height: 3px; border-radius: 3px; background: var(--blue); opacity: .35" />
    </div>

    <section v-else-if="block.type === 'text'" class="poster poster--amber">
        <span class="poster__badge"><span class="emoji">📖</span> Мәтін</span>
        <p v-for="(line, i) in paragraphs" :key="i" class="read mb-3">{{ line }}</p>
    </section>

    <section v-else-if="block.type === 'vocabulary'" class="poster poster--green">
        <span class="poster__badge"><span class="emoji">📗</span> Жаңа сөздер</span>
        <table class="vocab-table">
            <tbody>
                <tr v-for="(word, i) in words" :key="i">
                    <td>{{ word.kk }}</td>
                    <td>{{ word.ru }}</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section v-else-if="block.type === 'table'" class="poster poster--blue">
        <span class="poster__badge"><span class="emoji">📋</span> {{ block.text || 'Кесте' }}</span>
        <div style="overflow-x: auto">
            <table class="doc-table">
                <thead v-if="rows.length">
                    <tr>
                        <th v-for="(cell, i) in rows[0]" :key="i">{{ cell }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, r) in rows.slice(1)" :key="r">
                        <td v-for="(cell, c) in row" :key="c">{{ cell }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div v-else-if="block.type === 'image'" class="my-6">
        <v-row dense>
            <v-col v-for="(file, i) in files" :key="i" :cols="files.length > 1 ? 6 : 12">
                <v-img :src="file" rounded="xl" cover max-height="340" style="border: 3px solid var(--green)" />
            </v-col>
        </v-row>
        <div v-if="block.text" class="text-caption text-medium-emphasis mt-2">{{ block.text }}</div>
    </div>
</template>
