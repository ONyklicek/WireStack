/**
 * One write at a time per record, in the order the user made them.
 *
 * Every write of an inline-editable cell carries the record's optimistic-lock
 * version, and every write moves it. Two cells of one row committed one after
 * the other — tab out of "Type", type into "Maker", tab out again — are two
 * writes of one record, and the second has to be sent with the version the
 * FIRST one produced. Livewire cannot give it that: it holds back a call made
 * while another request of the component is in flight, but the call's
 * arguments are fixed when it is made, so the held-back write leaves with the
 * version the cell read before the first one landed. The server then refuses it
 * as somebody else's edit and the value is gone.
 *
 * So the cell does not hand its write to Livewire until the record's previous
 * write has settled, and reads its version only then. By that time the previous
 * write's answer has taught every cell of the record the new version (the
 * `wire-editable-committed` broadcast), so what leaves is exactly what the
 * server holds after the user's own writes — and nothing else. A write somebody
 * else made in between is still a different version, and still refused.
 *
 * A lane is keyed by host component and record key, so two rows, or two tables,
 * never wait for each other here (Livewire still sends them one by one, which
 * costs time, not correctness). A task that throws does not block the lane: the
 * next one runs after it either way.
 */

/**
 * Lane key -> the last write queued on it. On `window`: the bundle can run
 * twice, and two module copies with a lane map each would let a record's
 * writes run side by side again.
 *
 * @type {Map<string, Promise<unknown>>}
 */
const lanes = (window.__wireEditableLanes ??= new Map())

/** The lane a cell's writes share with every other cell of the same record. */
export const laneKey = (componentId, recordKey) => `${componentId ?? ''}\u0000${recordKey ?? ''}`

/**
 * Run `task` once every write queued before it on the same lane has settled.
 *
 * Resolves (or rejects) with what `task` does; the lane itself never rejects.
 */
export const runInLane = (key, task) => {
    const previous = lanes.get(key)

    // An idle lane runs the task NOW, in the caller's own tick, rather than one
    // microtask later. Not a shortcut: the call the task makes then reaches
    // Livewire exactly where it did before lanes existed — ahead of anything
    // the same handler does next (a fill handle released in the same gesture),
    // so the two still share a request in their original order.
    let run
    if (previous === undefined) {
        try {
            run = Promise.resolve(task())
        } catch (e) {
            run = Promise.reject(e)
        }
    } else {
        run = previous.then(task)
    }

    const settled = run.then(() => {}, () => {})

    lanes.set(key, settled)
    settled.then(() => {
        if (lanes.get(key) === settled) lanes.delete(key)
    })

    return run
}
