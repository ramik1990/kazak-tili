export function percent(done, total) {
    if (!total) return 0;
    return Math.round((done / total) * 100);
}

export function countWords(text) {
    return (text || '').trim().split(/\s+/).filter(Boolean).length;
}

export function splitBlanks(text) {
    return (text || '').split(/_{3,}/);
}
