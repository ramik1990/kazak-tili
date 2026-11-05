<script setup>
import { reactive, ref } from 'vue';
import { authApi } from '@/api';
import { parseError } from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';

const auth = useAuthStore();
const ui = useUiStore();
const { t, set } = useI18n();

const form = reactive({
    name: auth.user?.name ?? '',
    email: auth.user?.email ?? '',
    locale: auth.user?.locale ?? 'kk',
    current_password: '',
    password: '',
    password_confirmation: '',
});

const errors = ref({});
const busy = ref(false);

async function save() {
    busy.value = true;
    errors.value = {};
    try {
        const { data } = await authApi.updateProfile(form);
        auth.user = data.data;
        set(data.data.locale);
        form.current_password = '';
        form.password = '';
        form.password_confirmation = '';
        ui.success(t('common.save'));
    } catch (error) {
        const parsed = parseError(error);
        errors.value = parsed.errors;
        ui.error(parsed.message);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="page-narrow mx-auto">
        <h1 class="display-title text-h5 mb-5">{{ t('nav.settings') }}</h1>

        <section class="poster poster--blue">
            <span class="poster__badge"><span class="emoji">⚙️</span> {{ t('nav.settings') }}</span>
            <v-form @submit.prevent="save">
                <v-text-field v-model="form.name" :label="t('auth.name')" :error-messages="errors.name" class="mb-3" />
                <v-text-field v-model="form.email" :label="t('auth.email')" type="email" :error-messages="errors.email" class="mb-3" />
                <v-select
                    v-model="form.locale"
                    :items="[{ title: 'Қазақша', value: 'kk' }, { title: 'Русский', value: 'ru' }]"
                    :label="t('common.language')"
                    class="mb-5"
                />

                <div class="font-weight-bold mb-2">{{ t('auth.password') }}</div>
                <v-text-field v-model="form.current_password" :label="t('auth.currentPassword')" type="password" :error-messages="errors.current_password" class="mb-3" />
                <v-text-field v-model="form.password" :label="t('auth.password')" type="password" :error-messages="errors.password" class="mb-3" />
                <v-text-field v-model="form.password_confirmation" :label="t('auth.passwordConfirm')" type="password" class="mb-5" />

                <v-btn type="submit" color="primary" size="large" :loading="busy">{{ t('common.save') }}</v-btn>
            </v-form>
        </section>
    </div>
</template>
