<script setup>
import { computed, reactive, ref } from 'vue';
import { answersApi } from '@/api';
import { parseError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';
import { countWords, splitBlanks } from '@/utils/format';
import SkillBadge from '@/components/SkillBadge.vue';

const props = defineProps({
    task: { type: Object, required: true },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['saved']);

const ui = useUiStore();
const auth = useAuthStore();
const { t } = useI18n();

const locked = computed(() => !auth.isAuthenticated);

const busy = ref(false);
const revealed = ref(false);
const answer = ref(props.task.answer ?? null);
const keyData = ref(null);

const textItems = computed(() => props.task.items.filter((item) => typeof item === 'string'));
const tableItems = computed(() => props.task.items.filter((item) => item && typeof item === 'object'));

const blanks = computed(() => {
    let counter = 0;
    return textItems.value.map((item) => splitBlanks(item).slice(0, -1).map(() => ++counter));
});

const state = reactive({
    values: { ...(answer.value?.payload?.input?.values ?? {}) },
    answers: { ...(answer.value?.payload?.input?.answers ?? {}) },
    text: answer.value?.payload?.input?.text ?? '',
    done: Boolean(answer.value?.payload?.input?.done),
});

const details = computed(() => answer.value?.payload?.details ?? []);
const checked = computed(() => answer.value?.status === 'checked');
const submitted = computed(() => answer.value?.status === 'submitted');
const waiting = computed(() => submitted.value && answer.value?.score === null);

const cardClass = computed(() => {
    if (!answer.value) return '';
    if (waiting.value) return 'task-card--waiting';
    if (answer.value.score === null) return '';
    return answer.value.score === answer.value.max_score ? 'task-card--correct' : 'task-card--wrong';
});

const scoreLabel = computed(() => {
    if (answer.value?.score === null || answer.value?.score === undefined) return null;
    return `${answer.value.score} / ${answer.value.max_score}`;
});

function detail(number) {
    return details.value.find((row) => Number(row.number) === Number(number)) ?? null;
}

function verdict(number) {
    const row = detail(number);
    return row === null ? null : row.correct;
}

function expected(number) {
    return detail(number)?.expected ?? null;
}

function payload() {
    switch (props.task.type) {
        case 'test':
            return { answers: state.answers };
        case 'true_false':
        case 'fill_blank':
        case 'mcq':
            return { values: state.values };
        case 'offline':
            return { done: state.done };
        default:
            return { text: state.text, values: state.values };
    }
}

async function submit(status = 'submitted') {
    busy.value = true;
    try {
        const { data } = await answersApi.save(props.task.id, payload(), status);
        answer.value = data.data;
        keyData.value = data.key;
        emit('saved', data.data);
        ui.success(status === 'draft' ? t('task.save') : t('task.check'));
    } catch (error) {
        ui.error(parseError(error).message);
    } finally {
        busy.value = false;
    }
}

function markDone() {
    state.done = true;
    submit();
}
</script>

<template>
    <v-card :class="['task-card', 'mb-5', cardClass]">
        <div class="d-flex flex-wrap align-center ga-2 px-5 pt-4">
            <v-chip size="small" variant="tonal" color="primary" class="font-weight-bold">
                {{ task.number }}-тапсырма
            </v-chip>
            <SkillBadge :skill="task.skill" />
            <v-spacer />
            <v-chip v-if="scoreLabel" size="small" :color="answer.score === answer.max_score ? 'success' : 'warning'" variant="flat">
                {{ t('task.score') }}: {{ scoreLabel }}
            </v-chip>
            <v-chip v-else-if="waiting" size="small" color="warning" variant="tonal" prepend-icon="mdi-clock-outline">
                {{ t('task.waiting') }}
            </v-chip>
        </div>

        <v-card-text class="pt-3">
            <p class="text-body-1 font-weight-medium mb-1">{{ task.prompt }}</p>
            <p v-if="task.instruction" class="text-caption text-medium-emphasis mb-3">{{ task.instruction }}</p>

            <div v-if="task.skill === 'listening'" class="mb-4">
                <audio v-if="task.audio" :src="task.audio" controls class="w-100" />
                <v-alert v-else type="info" variant="tonal" density="compact" icon="mdi-volume-off">
                    {{ t('task.audioMissing') }}
                </v-alert>
            </div>

            <template v-if="task.type === 'test'">
                <div v-for="question in task.questions" :key="question.number" class="mb-5">
                    <div class="d-flex ga-2">
                        <span class="font-weight-bold">{{ question.number }}.</span>
                        <span>{{ question.text }}</span>
                        <v-icon
                            v-if="verdict(question.number) !== null"
                            :icon="verdict(question.number) ? 'mdi-check-circle' : 'mdi-close-circle'"
                            :color="verdict(question.number) ? 'success' : 'error'"
                            size="20"
                        />
                    </div>
                    <v-radio-group
                        v-model="state.answers[question.number]"
                        :disabled="readonly || checked || locked"
                        density="compact"
                        hide-details
                        class="mt-1"
                    >
                        <v-radio
                            v-for="option in question.options"
                            :key="option.label"
                            :value="option.label"
                            :label="`${option.label}) ${option.text}`"
                        />
                    </v-radio-group>
                    <div v-if="verdict(question.number) === false" class="text-caption text-success mt-1">
                        {{ t('task.answerKey') }}: {{ expected(question.number) }}
                    </div>
                </div>
            </template>

            <template v-else-if="task.type === 'true_false'">
                <div v-for="(item, index) in textItems" :key="index" class="d-flex flex-wrap align-center ga-3 mb-3">
                    <span class="flex-grow-1">{{ index + 1 }}. {{ item }}</span>
                    <v-btn-toggle
                        v-model="state.values[index + 1]"
                        :disabled="readonly || checked || locked"
                        density="comfortable"
                        variant="outlined"
                        divided
                        rounded="lg"
                    >
                        <v-btn value="дұрыс" size="small">Дұрыс</v-btn>
                        <v-btn value="бұрыс" size="small">Бұрыс</v-btn>
                    </v-btn-toggle>
                    <v-icon
                        v-if="verdict(index + 1) !== null && verdict(index + 1) !== undefined"
                        :icon="verdict(index + 1) ? 'mdi-check-circle' : 'mdi-close-circle'"
                        :color="verdict(index + 1) ? 'success' : 'error'"
                    />
                </div>
            </template>

            <template v-else-if="task.type === 'fill_blank'">
                <div v-for="(item, index) in textItems" :key="index" class="mb-3">
                    <div class="d-flex flex-wrap align-center ga-2">
                        <template v-for="(part, p) in splitBlanks(item)" :key="p">
                            <span>{{ part }}</span>
                            <v-text-field
                                v-if="p < splitBlanks(item).length - 1"
                                v-model="state.values[blanks[index][p]]"
                                :disabled="readonly || checked || locked"
                                :error="verdict(blanks[index][p]) === false"
                                density="compact"
                                class="blank-input"
                                hide-details
                            />
                        </template>
                    </div>
                    <div v-if="blanks[index].some((n) => verdict(n) === false)" class="text-caption text-success">
                        {{ t('task.answerKey') }}:
                        <span v-for="n in blanks[index]" :key="n" class="mr-2">{{ expected(n) }}</span>
                    </div>
                </div>
            </template>

            <template v-else-if="task.type === 'mcq'">
                <v-radio-group v-model="state.values[1]" :disabled="readonly || checked || locked" density="compact" hide-details>
                    <v-radio
                        v-for="option in task.options"
                        :key="option.label"
                        :value="option.label"
                        :label="`${option.label}) ${option.text}`"
                    />
                </v-radio-group>
            </template>

            <template v-else-if="task.type === 'offline'">
                <ul v-if="textItems.length" class="read ml-5 mb-3">
                    <li v-for="(item, index) in textItems" :key="index">{{ item }}</li>
                </ul>
                <v-alert type="info" variant="tonal" density="compact" icon="mdi-account-voice">
                    {{ t('task.offline') }}
                </v-alert>
            </template>

            <template v-else>
                <ul v-if="textItems.length" class="read ml-5 mb-3">
                    <li v-for="(item, index) in textItems" :key="index">{{ item }}</li>
                </ul>
                <v-textarea
                    v-model="state.text"
                    :disabled="readonly || checked || locked"
                    :rows="task.type === 'essay' ? 8 : 4"
                    auto-grow
                    :label="t('task.yourAnswer')"
                />
                <div class="text-caption text-medium-emphasis mt-1">{{ countWords(state.text) }} {{ t('task.words') }}</div>
            </template>

            <div v-for="(item, i) in tableItems" :key="`tbl-${i}`" class="my-4" style="overflow-x: auto">
                <table class="doc-table">
                    <tbody>
                        <tr v-for="(row, r) in item.table" :key="r">
                            <td v-for="(cell, c) in row" :key="c">{{ cell }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <v-alert v-if="answer?.feedback" type="info" variant="tonal" density="compact" class="mt-3">
                {{ answer.feedback }}
            </v-alert>

            <v-expand-transition>
                <v-alert v-if="revealed && (keyData?.text || task.has_key)" type="success" variant="tonal" density="compact" class="mt-3">
                    <span class="font-weight-bold">{{ t('task.answerKey') }}:</span> {{ keyData?.text || '—' }}
                </v-alert>
            </v-expand-transition>
        </v-card-text>

        <v-card-actions v-if="locked" class="px-5 pb-4 pt-0 no-print">
            <v-icon icon="mdi-lock-outline" size="16" class="text-medium-emphasis mr-1" />
            <RouterLink :to="{ name: 'login' }" class="text-caption">{{ t('task.signInToAnswer') }}</RouterLink>
        </v-card-actions>

        <v-card-actions v-else-if="!readonly" class="px-5 pb-4 pt-0 no-print">
            <v-btn
                v-if="task.type === 'offline'"
                :color="state.done ? 'success' : 'primary'"
                variant="flat"
                :loading="busy"
                :prepend-icon="state.done ? 'mdi-check' : 'mdi-check-outline'"
                @click="markDone"
            >
                {{ t('task.done') }}
            </v-btn>
            <template v-else>
                <v-btn color="primary" variant="flat" :loading="busy" @click="submit()">
                    {{ task.auto_check ? t('task.check') : t('task.save') }}
                </v-btn>
                <v-btn v-if="checked" variant="text" @click="answer.status = 'draft'">{{ t('task.retry') }}</v-btn>
            </template>
            <v-spacer />
            <v-btn
                v-if="task.has_key && (submitted || checked)"
                variant="text"
                size="small"
                prepend-icon="mdi-key-outline"
                @click="revealed = !revealed"
            >
                {{ t('task.showKey') }}
            </v-btn>
        </v-card-actions>
    </v-card>
</template>
