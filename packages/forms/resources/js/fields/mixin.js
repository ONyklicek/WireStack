/**
 * Join controller parts into one object, getters included.
 *
 * Object spread (`{ ...calendar() }`) reads every getter once, at the spread,
 * against the part alone — so `get days()` would land as a frozen array and a
 * getter that calls the host would throw. Copying the property descriptors
 * keeps a getter a getter, evaluated against the joined controller. Later parts
 * win, the way spread order did.
 */
export const mix = (...parts) => Object.defineProperties(
    {},
    Object.assign({}, ...parts.map((part) => Object.getOwnPropertyDescriptors(part))),
)
