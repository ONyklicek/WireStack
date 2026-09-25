import { openPage, checker } from './lib/cdp.mjs';

/*
 * A board: a Page composing WithBoard, the workbench's tasks in lanes.
 *
 * What only a browser can say: that Livewire's `wire:sort` really binds
 * SortableJS to every lane under one group — the thing that lets a card leave
 * its lane at all — and that a drop's server answer re-renders the card into
 * the lane it was dropped in. The drag itself is SortableJS's code; like the
 * dashboard driver, this calls the method a drop calls rather than simulating
 * a pointer, and puts the card back afterwards.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url: `${base}/task-board`, shotPrefix: 'task-board', width: 1500, height: 900,
});

try {
  await waitFor(`!! window.Alpine && document.querySelectorAll('[data-testid^="board-lane-"]').length === 5`);
  await shot('01-board');

  check('the page draws its heading and header action around the board', await eval_(`
    document.querySelector('h1')?.textContent.trim() === 'Task board' && !! document.querySelector('[data-testid="action-refresh"]')
  `));

  const binding = await eval_(`(() => {
    const lists = [...document.querySelectorAll('[data-testid^="board-lane-"] ul')];
    const bound = (el) => Object.keys(el).some((key) => key.startsWith('Sortable'));
    return { lists: lists.length, bound: lists.filter(bound).length };
  })()`);
  check('SortableJS is bound to every lane', binding.lists === 5 && binding.bound === 5, JSON.stringify(binding));

  // Whichever task is first in To do — other drivers rename the workbench's
  // tasks, so the card is found by its lane and followed by its key.
  const key = await eval_(`document.querySelector('[data-testid="board-lane-todo"] [data-testid^="board-card-"]')?.getAttribute('data-testid').replace('board-card-', '') ?? null`);
  check('To do holds a card to move', key !== null, String(key));

  const inLane = (lane) => `document.querySelector('[data-testid="board-card-${key}"]')?.closest('[data-testid="board-lane-${lane}"]')`;

  await eval_(`window.Livewire.first().moveBoardCard(${JSON.stringify(key)}, 0, 'done'); true;`);
  await waitFor(`!! ${inLane('done')}`);
  await shot('02-moved');
  check('a drop re-renders the card in the lane it landed in', await eval_(`!! ${inLane('done')}`));

  await eval_(`window.Livewire.first().moveBoardCard(${JSON.stringify(key)}, 0, 'todo'); true;`);
  await waitFor(`!! ${inLane('todo')}`);
  check('and back, leaving the workbench as it was', await eval_(`!! ${inLane('todo')}`));
} catch (error) {
  check('the flow ran to the end', false, error.message);
  await shot('99-failed');
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
