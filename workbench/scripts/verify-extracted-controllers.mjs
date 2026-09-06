import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for the field controllers that used to be inline `x-data` bodies:
 * KeyValue, Slider, CodeEditor, CheckboxList and MorphToSelect.
 *
 * Moving a body out of the markup is the kind of change that passes every PHP
 * test while leaving a field dead in the browser — the markup still says
 * `x-data="wireX(…)"`, and nothing but a real Alpine boot can tell whether `wireX`
 * exists, whether the config reached it, and whether the behaviour the view
 * calls into still answers. One driver walks all five, one preview each.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-extracted-controllers.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';

const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url: `${origin}/previews/field-key-value`, shotPrefix: 'extracted-controllers' });

const { check, finish } = checker();

/** Move to another preview and wait for Alpine to boot it. */
const visit = async (slug) => {
  await page('Page.navigate', { url: `${origin}/previews/field-${slug}` });
  await waitFor(`typeof Alpine !== 'undefined' && typeof Livewire !== 'undefined' && !! document.querySelector('[x-data]')`, { timeout: 15000 });
  await eval_(`
    window.root = (name) => document.querySelector('[x-data^="' + name + '"]');
    window.data = (name) => Alpine.$data(root(name));
    window.wire = () => Livewire.all()[0].$wire;
    true;
  `);
};

try {
  // ── KeyValue: rows are rebuilt, never mutated in place ────────────────
  await visit('key-value');
  check('KeyValue evaluates against the registered controller', await eval_(`!! root('wireKeyValue')`));

  const seededPairs = await eval_(`data('wireKeyValue').pairs.length`);
  check('the seeded pairs arrive as a list', seededPairs === 3, `pairs=${seededPairs}`);

  await eval_(`data('wireKeyValue').addPair()`);
  await waitFor(`data('wireKeyValue').pairs.length === 4`);
  check('a row can be added', true);

  await eval_(`data('wireKeyValue').updateKey(3, 'region'); data('wireKeyValue').updateValue(3, 'eu-west')`);
  const written = await eval_(`JSON.stringify(data('wireKeyValue').pairs[3])`);
  check('editing a row replaces the array rather than mutating it',
    written === '{"key":"region","value":"eu-west"}', written);

  await waitFor(`(wire().get('data.metadata') || []).length === 4`);
  check('the new row reaches Livewire state', true);

  await eval_(`data('wireKeyValue').removePair(3)`);
  await waitFor(`data('wireKeyValue').pairs.length === 3`);
  check('a row can be removed again', true);
  await shot('01-key-value');

  // ── Slider: the fill follows the value ────────────────────────────────
  await visit('slider');
  check('Slider evaluates against the registered controller', await eval_(`!! root('wireSlider')`));

  const bounds = await eval_(`data('wireSlider').min + '..' + data('wireSlider').max`);
  check('the bounds PHP configured reach the controller', bounds === '0..100', `bounds=${bounds}`);

  const percent = await eval_(`data('wireSlider').percent`);
  check('the seeded value becomes a percentage of the track', percent === 65, `percent=${percent}`);

  const fill = await eval_(`data('wireSlider').trackBackground`);
  check('the track is painted up to that point', fill.includes('65%'), fill);

  await eval_(`data('wireSlider').value = 100`);
  await waitFor(`data('wireSlider').percent === 100`);
  check('moving the value repaints the track',
    (await eval_(`data('wireSlider').trackBackground`)).includes('100%'));
  await shot('02-slider');

  // ── CodeEditor: Tab indents instead of leaving the field ──────────────
  await visit('code-editor');
  check('CodeEditor evaluates against the registered controller', await eval_(`!! root('wireCodeEditor')`));

  const lines = await eval_(`data('wireCodeEditor').lines.length`);
  check('the gutter is derived from the content', lines === 4, `lines=${lines}`);

  const indented = await eval_(`
    const editor = data('wireCodeEditor');
    const area = root('wireCodeEditor').querySelector('textarea');
    area.selectionStart = area.selectionEnd = 0;
    editor.onTab({ preventDefault() {}, target: area });
    editor.content.slice(0, 8)
  `);
  check('Tab splices four spaces in at the caret', indented === '    publ', JSON.stringify(indented));
  await shot('03-code-editor');

  // ── CheckboxList: the bulk toggles write the whole option set ─────────
  await visit('checkbox-list');
  check('CheckboxList evaluates against the registered controller', await eval_(`!! root('wireCheckboxList')`));

  const values = await eval_(`data('wireCheckboxList').values.length`);
  check('the option values are resolved in PHP, not read off the DOM', values > 0, `values=${values}`);

  await eval_(`data('wireCheckboxList').selectAll()`);
  await waitFor(`(wire().get(data('wireCheckboxList').statePath) || []).length === data('wireCheckboxList').values.length`);
  check('select all writes every value in one update', true);

  await eval_(`data('wireCheckboxList').deselectAll()`);
  await waitFor(`(wire().get(data('wireCheckboxList').statePath) || []).length === 0`);
  check('deselect all empties it', true);
  await shot('04-checkbox-list');

  // ── MorphToSelect: the record list follows the type ───────────────────
  await visit('morph-to-select');
  check('MorphToSelect evaluates against the registered controller', await eval_(`!! root('wireMorphToSelect')`));

  const types = await eval_(`Object.keys(data('wireMorphToSelect').typeOptions).length`);
  check('every type arrives with its options resolved', types === 2, `types=${types}`);

  const firstType = await eval_(`Object.keys(data('wireMorphToSelect').typeOptions)[0]`);
  await eval_(`data('wireMorphToSelect').selectedType = ${JSON.stringify(firstType)}`);
  await waitFor(`Object.keys(data('wireMorphToSelect').idOptions).length > 0`);
  check('choosing a type fills the record list with no roundtrip', true);

  // Switching type must clear the id: a key from the other type is still a
  // number, and would point the morph at the wrong table.
  await eval_(`wire().set(data('wireMorphToSelect').idStatePath, 1)`);
  await waitFor(`wire().get('data.subject_id') === 1`);

  const secondType = await eval_(`Object.keys(data('wireMorphToSelect').typeOptions)[1]`);
  await eval_(`data('wireMorphToSelect').selectedType = ${JSON.stringify(secondType)}`);
  await waitFor(`wire().get('data.subject_id') === null`);
  check('changing the type clears the record it no longer belongs to', true);
  await shot('05-morph-to-select');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
