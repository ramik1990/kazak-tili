<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { authNotice, parseError } from '@/api/http';
import { useI18n } from '@/composables/useI18n';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { t, locale, toggle } = useI18n();

const form = reactive({ email: '', password: '' });
const errors = ref({});
const message = ref('');
const busy = ref(false);

onMounted(() => {
    message.value = authNotice.take() || '';
});

async function submit() {
    busy.value = true;
    errors.value = {};
    message.value = '';
    try {
        await auth.login(form);
        router.push(route.query.redirect || auth.home);
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
            <div style="width: 100%; max-width: 440px">
                <div class="text-center mb-7">
                    <v-avatar color="primary" size="64" rounded="xl" class="mb-4">
                        <v-icon icon="mdi-leaf" size="34" color="white" />
                    </v-avatar>
                    <h1 class="display-title text-h4">{{ t('appName') }}</h1>
                    <div class="text-medium-emphasis mt-1">{{ t('appSubtitle') }}</div>
                </div>

                <section class="poster poster--green">
                    <span class="poster__badge"><span class="emoji">🔑</span> {{ t('auth.signIn') }}</span>
                    <v-form @submit.prevent="submit">
                        <v-alert v-if="message" type="error" variant="tonal" density="compact" class="mb-4">{{ message }}</v-alert>

                        <v-text-field
                            v-model="form.email"
                            :label="t('auth.email')"
                            type="email"
                            prepend-inner-icon="mdi-email-outline"
                            :error-messages="errors.email"
                            class="mb-3"
                            autofocus
                        />
                        <v-text-field
                            v-model="form.password"
                            :label="t('auth.password')"
                            type="password"
                            prepend-inner-icon="mdi-lock-outline"
                            :error-messages="errors.password"
                            class="mb-4"
                        />
                        <v-btn type="submit" color="primary" size="large" block :loading="busy">{{ t('auth.signIn') }}</v-btn>
                    </v-form>

                    <div class="text-center mt-4 text-body-2">
                        {{ t('auth.noAccount') }}
                        <RouterLink :to="{ name: 'register' }" class="font-weight-bold">{{ t('auth.signUp') }}</RouterLink>
                    </div>
                </section>

                <div class="text-center mt-4">
                    <v-btn variant="text" size="small" @click="toggle">
                        {{ locale === 'kk' ? 'Русский' : 'Қазақша' }}
                    </v-btn>
                </div>
            </div>
        </v-container>
    </v-main>
</template>
