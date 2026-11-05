import http from './http';

export const authApi = {
    login: (payload) => http.post('/auth/login', payload),
    register: (payload) => http.post('/auth/register', payload),
    logout: () => http.post('/auth/logout'),
    me: () => http.get('/auth/me'),
    groups: () => http.get('/auth/groups'),
    updateProfile: (payload) => http.put('/auth/profile', payload),
};

export const textbookApi = {
    index: () => http.get('/textbook'),
    section: (id) => http.get(`/sections/${id}`),
    lesson: (id) => http.get(`/lessons/${id}`),
    glossary: (params) => http.get('/glossary', { params }),
    search: (q) => http.get('/search', { params: { q } }),
};

export const answersApi = {
    mine: () => http.get('/answers'),
    save: (taskId, payload, status = 'submitted') => http.post(`/tasks/${taskId}/answer`, { payload, status }),
};

export const teacherApi = {
    answers: (params) => http.get('/teacher/answers', { params }),
    check: (id, payload) => http.put(`/teacher/answers/${id}`, payload),
    groups: () => http.get('/teacher/groups'),
};

export const adminApi = {
    tasks: (params) => http.get('/admin/tasks', { params }),
    updateTask: (id, payload) => http.put(`/admin/tasks/${id}`, payload),
    uploadAudio(id, file) {
        const form = new FormData();
        form.append('audio', file);
        return http.post(`/admin/tasks/${id}/audio`, form, { headers: { 'Content-Type': 'multipart/form-data' } });
    },
    deleteAudio: (id) => http.delete(`/admin/tasks/${id}/audio`),
    users: (params) => http.get('/admin/users', { params }),
    createUser: (payload) => http.post('/admin/users', payload),
    updateUser: (id, payload) => http.put(`/admin/users/${id}`, payload),
    removeUser: (id) => http.delete(`/admin/users/${id}`),
    groups: () => http.get('/admin/groups'),
    createGroup: (payload) => http.post('/admin/groups', payload),
    updateGroup: (id, payload) => http.put(`/admin/groups/${id}`, payload),
    removeGroup: (id) => http.delete(`/admin/groups/${id}`),
};
