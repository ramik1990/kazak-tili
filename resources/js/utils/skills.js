export const SKILLS = {
    reading: { icon: '📖', tone: 'blue' },
    listening: { icon: '🎧', tone: 'violet' },
    speaking: { icon: '🗣️', tone: 'pink' },
    writing: { icon: '✍️', tone: 'amber' },
    literature: { icon: '📚', tone: 'red' },
    project: { icon: '🌿', tone: 'green' },
};

export const TONES = ['green', 'blue', 'amber', 'violet', 'pink'];

export function toneOf(skill) {
    return SKILLS[skill]?.tone ?? 'blue';
}

export function iconOf(skill) {
    return SKILLS[skill]?.icon ?? '📖';
}
