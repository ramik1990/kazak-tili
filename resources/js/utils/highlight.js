const LETTERS = 'a-zA-Zа-яёА-ЯЁәғқңөұүһіӘҒҚҢӨҰҮҺІ';

const MAX_SUFFIX = 6;

function escapeRe(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function collectTerms(sources) {
    const map = new Map();

    for (const { kk, ru, kind } of sources) {
        const term = (kk || '').trim();

        if (term.length < 4) {
            continue;
        }

        const key = term.toLowerCase();

        if (!map.has(key)) {
            map.set(key, { kk: term, ru: (ru || '').trim(), kind: kind || 'support' });
        }
    }

    return [...map.values()].sort((a, b) => b.kk.length - a.kk.length);
}

export function highlight(text, terms) {
    if (!text || terms.length === 0) {
        return [{ text }];
    }

    const pattern = terms.map((term) => escapeRe(term.kk)).join('|');
    const re = new RegExp(`(?<![${LETTERS}])(${pattern})([${LETTERS}]{0,${MAX_SUFFIX}})(?![${LETTERS}])`, 'giu');
    const index = new Map(terms.map((term) => [term.kk.toLowerCase(), term]));

    const chunks = [];
    let last = 0;
    let match;

    while ((match = re.exec(text)) !== null) {
        const term = index.get(match[1].toLowerCase());

        if (!term) {
            continue;
        }

        if (match.index > last) {
            chunks.push({ text: text.slice(last, match.index) });
        }

        chunks.push({ text: match[0], term });
        last = match.index + match[0].length;
    }

    if (last < text.length) {
        chunks.push({ text: text.slice(last) });
    }

    return chunks.length ? chunks : [{ text }];
}
