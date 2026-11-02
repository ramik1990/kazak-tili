<script setup>
import { onMounted, reactive, ref } from 'vue';
import { adminApi } from '@/api';
import { parseError } from '@/api/http';
import { useUiStore } from '@/stores/ui';
import { useI18n } from '@/composables/useI18n';

const ui = useUiStore();
const { t } = useI18n();

const users = ref([]);
const groups = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(true);
const tab = ref('users');
const filters = reactive({ search: '', role: null, page: 1 });

const dialog = reactive({ open: false, id: null, model: {} });
const groupDialog = reactive({ open: false, id: null, model: {} });
const errors = ref({});

const roles = [
    { title: 'Студент', value: 'student' },
    { title: 'Оқытушы', value: 'teacher' },
    { title: 'Әкімші', value: 'admin' },
];

async function load() {
    loading.value = true;
    try {
        const { data } = await adminApi.users({
            search: filters.search || undefined,
            role: filters.role || undefined,
            page: filters.page,
        });
        users.value = data.data;
        meta.value = data.meta;
    } catch (error) {
        ui.error(parseError(error).message);
    } finally {
        loading.value = false;
    }
}

async function loadGroups() {
    const { data } = await adminApi.groups();
    groups.value = data.data;
}

function openUser(user = null) {
    errors.value = {};
    dialog.id = user?.id ?? null;
    dialog.model = user
        ? { name: user.name, email: user.email, role: user.role, group_id: user.group?.id ?? null, is_active: user.is_active, password: '' }
        : { name: '', email: '', role: 'student', group_id: null, is_active: true, password: '' };
    dialog.open = true;
}

async function saveUser() {
    errors.value = {};
    try {
        const payload = { ...dialog.model };
        if (!payload.password) delete payload.password;
        if (dialog.id) {
            await adminApi.updateUser(dialog.id, payload);
        } else {
            await adminApi.createUser(payload);
        }
        dialog.open = false;
        ui.success(t('common.save'));
        load();
    } catch (error) {
        const parsed = parseError(error);
        errors.value = parsed.errors;
        ui.error(parsed.message);
    }
}

async function removeUser(user) {
    const ok = await ui.confirm({ title: `${user.name} — жою?`, confirmText: 'Жою' });
    if (!ok) return;
    await adminApi.removeUser(user.id);
    ui.success('Жойылды.');
    load();
}

function openGroup(group = null) {
    errors.value = {};
    groupDialog.id = group?.id ?? null;
    groupDialog.model = group
        ? { name: group.name, speciality: group.speciality, year: group.year, teacher_id: group.teacher_id, is_active: group.is_active }
        : { name: '', speciality: '', year: 1, teacher_id: null, is_active: true };
    groupDialog.open = true;
}

async function saveGroup() {
    errors.value = {};
    try {
        if (groupDialog.id) {
            await adminApi.updateGroup(groupDialog.id, groupDialog.model);
        } else {
            await adminApi.createGroup(groupDialog.model);
        }
        groupDialog.open = false;
        ui.success(t('common.save'));
        loadGroups();
    } catch (error) {
        const parsed = parseError(error);
        errors.value = parsed.errors;
        ui.error(parsed.message);
    }
}

onMounted(() => {
    load();
    loadGroups();
});
</script>

<template>
    <div>
        <div class="d-flex flex-wrap align-center ga-3 mb-5">
            <h1 class="display-title text-h5">{{ t('nav.users') }}</h1>
            <v-spacer />
            <v-btn-toggle v-model="tab" mandatory density="comfortable" variant="outlined" divided rounded="lg">
                <v-btn value="users" size="small">Пайдаланушылар</v-btn>
                <v-btn value="groups" size="small">Топтар</v-btn>
            </v-btn-toggle>
        </div>

        <template v-if="tab === 'users'">
            <div class="d-flex flex-wrap ga-3 mb-4">
                <v-text-field
                    v-model="filters.search"
                    :label="t('common.search')"
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    style="max-width: 320px"
                    @update:model-value="filters.page = 1; load()"
                />
                <v-select
                    v-model="filters.role"
                    :items="roles"
                    label="Рөлі"
                    clearable
                    style="max-width: 200px"
                    @update:model-value="filters.page = 1; load()"
                />
                <v-spacer />
                <v-btn color="primary" prepend-icon="mdi-account-plus" @click="openUser()">Қосу</v-btn>
            </div>

            <v-skeleton-loader v-if="loading" type="table" />

            <v-card v-else class="pa-2" border>
                <v-table density="comfortable">
                    <thead>
                        <tr>
                            <th>Аты</th>
                            <th>E-mail</th>
                            <th>Рөлі</th>
                            <th>Тобы</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td class="font-weight-medium">{{ user.name }}</td>
                            <td class="text-medium-emphasis">{{ user.email }}</td>
                            <td><v-chip size="x-small" variant="tonal">{{ user.role }}</v-chip></td>
                            <td>{{ user.group?.name ?? '—' }}</td>
                            <td class="text-end text-no-wrap">
                                <v-btn icon="mdi-pencil" size="small" variant="text" @click="openUser(user)" />
                                <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="removeUser(user)" />
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card>

            <div v-if="meta.last_page > 1" class="d-flex justify-center mt-4">
                <v-pagination v-model="filters.page" :length="meta.last_page" :total-visible="7" @update:model-value="load" />
            </div>
        </template>

        <template v-else>
            <div class="d-flex mb-4">
                <v-spacer />
                <v-btn color="primary" prepend-icon="mdi-plus" @click="openGroup()">Топ қосу</v-btn>
            </div>

            <v-card class="pa-2" border>
                <v-table density="comfortable">
                    <thead>
                        <tr>
                            <th>Атауы</th>
                            <th>Мамандығы</th>
                            <th>Курс</th>
                            <th>Оқытушы</th>
                            <th class="text-end">Студент</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="group in groups" :key="group.id">
                            <td class="font-weight-medium">{{ group.name }}</td>
                            <td class="text-medium-emphasis">{{ group.speciality }}</td>
                            <td>{{ group.year }}</td>
                            <td>{{ group.teacher?.name ?? '—' }}</td>
                            <td class="text-end">{{ group.students_count }}</td>
                            <td class="text-end">
                                <v-btn icon="mdi-pencil" size="small" variant="text" @click="openGroup(group)" />
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </v-card>
        </template>

        <v-dialog v-model="dialog.open" max-width="520">
            <v-card class="pa-4" rounded="xl">
                <v-card-title>{{ dialog.id ? 'Өзгерту' : 'Жаңа пайдаланушы' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-model="dialog.model.name" :label="t('auth.name')" :error-messages="errors.name" class="mb-3" />
                    <v-text-field v-model="dialog.model.email" :label="t('auth.email')" :error-messages="errors.email" class="mb-3" />
                    <v-select v-model="dialog.model.role" :items="roles" label="Рөлі" class="mb-3" />
                    <v-select
                        v-model="dialog.model.group_id"
                        :items="groups"
                        item-title="name"
                        item-value="id"
                        :label="t('auth.group')"
                        clearable
                        class="mb-3"
                    />
                    <v-text-field
                        v-model="dialog.model.password"
                        :label="t('auth.password')"
                        type="text"
                        :error-messages="errors.password"
                        :hint="dialog.id ? 'Бос қалдырсаңыз — өзгермейді' : ''"
                        persistent-hint
                        class="mb-3"
                    />
                    <v-switch v-model="dialog.model.is_active" color="primary" label="Белсенді" hide-details />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog.open = false">{{ t('common.cancel') }}</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveUser">{{ t('common.save') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="groupDialog.open" max-width="520">
            <v-card class="pa-4" rounded="xl">
                <v-card-title>{{ groupDialog.id ? 'Топты өзгерту' : 'Жаңа топ' }}</v-card-title>
                <v-card-text>
                    <v-text-field v-model="groupDialog.model.name" label="Атауы" :error-messages="errors.name" class="mb-3" />
                    <v-text-field v-model="groupDialog.model.speciality" label="Мамандығы" class="mb-3" />
                    <v-text-field v-model.number="groupDialog.model.year" label="Курс" type="number" min="1" max="6" class="mb-3" />
                    <v-select
                        v-model="groupDialog.model.teacher_id"
                        :items="users.filter((user) => user.role !== 'student')"
                        item-title="name"
                        item-value="id"
                        label="Оқытушы"
                        clearable
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="groupDialog.open = false">{{ t('common.cancel') }}</v-btn>
                    <v-btn color="primary" variant="flat" @click="saveGroup">{{ t('common.save') }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
