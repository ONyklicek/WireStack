/**
 * What "matches the search" means for every list filtered in the browser —
 * select options, a checkbox list, the admin menu.
 *
 * Two rules, the same ones the server applies (`SearchTermParser` with its
 * default `tokenize()`, and `SearchText` for a search answered in PHP):
 *
 * - **word by word**: `novak praha` keeps an entry containing both words, in
 *   any order; a "quoted phrase" stays one word;
 * - **case and accents ignored**: `novak` finds `Novák`, `cerny` finds `Černý`.
 *
 * Keeping it here rather than as a `.includes()` per surface is what stops a
 * select and a table from disagreeing about the same term.
 */

/** Lower-case, with the accents taken off: `Černý` → `cerny`. */
export const fold = (text) =>
    String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()

/** The folded words of a term; a double-quoted run is kept as one word. */
export const searchWords = (term) => {
    const words = []

    for (const [, phrase, word] of fold(term).matchAll(/"([^"]*)"|(\S+)/g)) {
        const value = (phrase ?? word).trim()

        if (value !== '') words.push(value)
    }

    return words
}

/**
 * A predicate answering whether a text contains every word of the term.
 *
 * Built once per term and asked once per entry, so a long list folds the term
 * a single time. A blank term matches everything.
 */
export const searchMatcher = (term) => {
    const words = searchWords(term)

    if (words.length === 0) return () => true

    return (text) => {
        const haystack = fold(text)

        return words.every((word) => haystack.includes(word))
    }
}

/** One-off form of {@link searchMatcher}. */
export const matchesSearch = (text, term) => searchMatcher(term)(text)
