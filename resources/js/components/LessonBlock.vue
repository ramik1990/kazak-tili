<script setup>
import { computed } from 'vue';

const props = defineProps({ block: { type: Object, required: true } });

const rows = computed(() => props.block.payload?.rows ?? []);
const words = computed(() => props.block.payload?.words ?? []);
const files = computed(() => props.block.payload?.files ?? []);
</script>

<template>
    <div v-if="block.type === 'heading'" class="mt-8 mb-3">
        <h3 class="text-h6 font-weight-bold">{{ block.text }}</h3>
        <div class="leaf-divider mt-2" style="max-width: 120px" />
    </div>

    <p v-else-if="block.type === 'text'" class="read mb-4">{{ block.text }}</p>

    <v-card v-else-if="block.type === 'vocabulary'" class="my-5 pa-1" color="surface-variant">
        <v-card-title class="text-subtitle-1 font-weight-bold d-flex align-center ga-2">
            <v-icon icon="mdi-book-alphabet" size="20" />
            Жаңа сөздер
        </v-card-title>
        <v-card-text>
            <table class="vocab-table">
                <tbody>
                    <tr v-for="(word, i) in words" :key="i">
                        <td>{{ word.kk }}</td>
                        <td class="text-medium-emphasis">{{ word.ru }}</td>
                    </tr>
                </tbody>
            </table>
        </v-card-text>
    </v-card>

    <div v-else-if="block.type === 'table'" class="my-5">
        <div v-if="block.text" class="text-subtitle-2 font-weight-bold mb-2">{{ block.text }}</div>
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
    </div>

    <div v-else-if="block.type === 'image'" class="my-6">
        <v-row dense>
            <v-col v-for="(file, i) in files" :key="i" :cols="files.length > 1 ? 6 : 12">
                <v-img :src="file" rounded="xl" cover max-height="340" />
            </v-col>
        </v-row>
        <div v-if="block.text" class="text-caption text-medium-emphasis mt-2">{{ block.text }}</div>
    </div>
</template>
