<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { authApi } from '@/api';
import { parseError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useI18n } from '@/composables/useI18n';

const auth = useAuthStore();
const router = useRouter();
const { t, locale, toggle } = useI18n();

const form = reactive({ name: '', email: '', password: '', password_confirmation: '', group_id: null });
const groups = ref([]);
const errors = ref({});
const message = ref('');
const busy = ref(false);

onMounted(async () => {
    try {
        const { data } = await authApi.groups();
        groups.value = data.data;
    } catch {
        groups.value = [];
    }
});

async function submit() {
    busy.value = true;
    errors.value = {};
    message.value = '';
    try {
        await auth.register({ ...form, locale: locale.value });
        router.push(auth.home);
    } catch (error) {
        const parsed = parseError(error);
        errors.value = parsed.errors;
        message.value = parsed.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <v-main class="app-shell">
        <v-container class="d-flex align-center justify-center" style="min-height: 100vh">
            <div style="width: 100%; max-width: 460px">
                <div class="text-center mb-6">
                    <v-avatar color="primary" size="56" rounded="xl" class="mb-3">
                        <v-icon icon="mdi-leaf" size="30" color="white" />
                    </v-avatar>
                    <h1 class="display-title text-h5">{{ t('auth.signUp') }}</h1>
                </div>

                <v-card class="pa-6" border>
                    <v-form @submit.prevent="submit">
                        <v-alert v-if="message" type="error" variant="tonal" density="compact" class="mb-4">{{ message }}</v-alert>

                        <v-text-field v-model="form.name" :label="t('auth.name')" :error-messages="errors.name" class="mb-3" autofocus />
                        <v-text-field v-model="form.email" :label="t('auth.email')" type="email" :error-messages="errors.email" class="mb-3" />
                        <v-select
                            v-model="form.group_id"
                            :items="groups"
                            item-title="name"
                            item-value="id"
                            :label="t('auth.group')"
                            :error-messages="errors.group_id"
                            class="mb-3"
                        />
                        <v-text-field v-model="form.password" :label="t('auth.password')" type="password" :error-messages="errors.password" class="mb-3" />
                        <v-text-field v-model="form.password_confirmation" :label="t('auth.passwordConfirm')" type="password" class="mb-4" />

                        <v-btn type="submit" color="primary" size="large" block :loading="busy">{{ t('auth.signUp') }}</v-btn>
                    </v-form>

                    <div class="text-center mt-4 text-body-2">
                        {{ t('auth.hasAccount') }}
                        <RouterLink :to="{ name: 'login' }" class="font-weight-bold">{{ t('auth.signIn') }}</RouterLink>
                    </div>
                </v-card>

                <div class="text-center mt-4">
                    <v-btn variant="text" size="small" @click="toggle">
                        {{ locale === 'kk' ? 'Русский' : 'Қазақша' }}
                    </v-btn>
                </div>
            </div>
        </v-container>
    </v-main>
</template>
