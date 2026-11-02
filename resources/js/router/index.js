import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
    { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue'), meta: { guest: true } },
    { path: '/register', name: 'register', component: () => import('@/pages/RegisterPage.vue'), meta: { guest: true } },

    { path: '/', redirect: { name: 'textbook' } },
    { path: '/textbook', name: 'textbook', component: () => import('@/pages/TextbookPage.vue'), meta: { public: true } },
    { path: '/sections/:id(\\d+)', name: 'section', component: () => import('@/pages/SectionPage.vue'), props: true, meta: { public: true } },
    { path: '/lessons/:id(\\d+)', name: 'lesson', component: () => import('@/pages/LessonPage.vue'), props: true, meta: { public: true } },
    { path: '/glossary', name: 'glossary', component: () => import('@/pages/GlossaryPage.vue'), meta: { public: true } },
    { path: '/progress', name: 'progress', component: () => import('@/pages/ProgressPage.vue') },
    { path: '/settings', name: 'settings', component: () => import('@/pages/SettingsPage.vue') },

    { path: '/teacher/review', name: 'review', component: () => import('@/pages/teacher/ReviewPage.vue'), meta: { teacher: true } },
    { path: '/teacher/journal', name: 'journal', component: () => import('@/pages/teacher/JournalPage.vue'), meta: { teacher: true } },

    { path: '/admin/tasks', name: 'admin.tasks', component: () => import('@/pages/admin/AdminTasksPage.vue'), meta: { admin: true } },
    { path: '/admin/users', name: 'admin.users', component: () => import('@/pages/admin/AdminUsersPage.vue'), meta: { admin: true } },

    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: (to, from, saved) => saved ?? { top: 0 },
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.isAuthenticated) {
        if (to.meta.guest || to.meta.public) return true;

        return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
    }

    if (!auth.user) {
        try {
            await auth.fetchUser();
        } catch {
            auth.clearSession();
            return { name: 'login' };
        }
    }

    if (to.meta.guest) return auth.home;
    if (to.meta.admin && !auth.isAdmin) return auth.home;
    if (to.meta.teacher && !auth.isTeacher) return auth.home;

    return true;
});

export default router;
