/**
 * Tab-separated text, the way a spreadsheet puts a block of cells on the
 * clipboard: rows end with a newline, cells with a tab, and a cell holding
 * either is wrapped in double quotes, with a quote inside written twice.
 *
 * Kept apart from the controller so the parsing — which is where a paste goes
 * wrong — can be read and tested on its own.
 */

/** Whether pasted text is a block rather than one value for one cell. */
export const isBlock = (text) => typeof text === 'string' && /[\t\n\r]/.test(text.replace(/[\r\n]+$/, ''))

/**
 * @param {string} text
 * @returns {string[][]} rows of cells; a trailing line break adds no empty row
 */
export const parseTsv = (text) => {
    const rows = []
    let row = []
    let cell = ''
    let quoted = false

    for (let i = 0; i < text.length; i++) {
        const ch = text[i]

        if (quoted) {
            if (ch === '"' && text[i + 1] === '"') {
                cell += '"'
                i++
            } else if (ch === '"') {
                quoted = false
            } else {
                cell += ch
            }

            continue
        }

        if (ch === '"' && cell === '') {
            quoted = true
        } else if (ch === '\t') {
            row.push(cell)
            cell = ''
        } else if (ch === '\n' || ch === '\r') {
            if (ch === '\r' && text[i + 1] === '\n') i++
            row.push(cell)
            rows.push(row)
            row = []
            cell = ''
        } else {
            cell += ch
        }
    }

    if (cell !== '' || row.length > 0) {
        row.push(cell)
        rows.push(row)
    }

    return rows
}

/** The other way: cells to tab-separated text a spreadsheet pastes as a block. */
export const toTsv = (rows) => rows
    .map((cells) => cells.map((value) => {
        const text = value === null || value === undefined ? '' : String(value)

        return /[\t\n\r"]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text
    }).join('\t'))
    .join('\n')
