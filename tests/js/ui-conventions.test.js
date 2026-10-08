import { test } from 'node:test';
import assert from 'node:assert/strict';

import { checkSource } from '../../scripts/check-ui-conventions.mjs';

const rules = (file, source) => checkSource(file, source).map((problem) => problem.rule);

test('flags palette, black/white, arbitrary, and literal colors', () => {
    assert.deepEqual(rules('resources/views/a.blade.php', '<div class="bg-blue-500"></div>'), ['palette-class']);
    assert.deepEqual(rules('resources/views/a.blade.php', '<div class="hover:text-white"></div>'), ['black-white-class']);
    assert.deepEqual(rules('resources/views/a.blade.php', '<div class="bg-[#ff0000]"></div>'), ['color-literal', 'arbitrary-color']);
    assert.deepEqual(rules('resources/css/app.css', '.x { color: rgb(0 0 0); }'), ['color-literal']);
});

test('flags inline style colors, Alpine CDN, and dynamic color classes', () => {
    assert.deepEqual(rules('resources/views/a.blade.php', '<p style="color: var(--x)">'), ['inline-style-color']);
    assert.deepEqual(rules('resources/views/a.blade.php', '<script src="https://unpkg.com/alpinejs"></script>'), ['alpine-cdn']);
    assert.deepEqual(rules('resources/js/a.js', "el.className = 'bg-' + tone;"), ['dynamic-color-class']);
});

test('Alpine.start is only allowed in the bootstrap file', () => {
    assert.deepEqual(rules('resources/js/other.js', 'Alpine.start();'), ['alpine-start']);
    assert.deepEqual(rules('resources/js/app.js', 'Alpine.start();'), []);
    assert.deepEqual(rules('resources/js/other.js', ' * Called before Alpine.start().'), []);
});

test('semantic tokens, anchors, and arbitrary dimensions pass', () => {
    assert.deepEqual(rules('resources/views/a.blade.php', '<a href="#main-content" class="w-[20rem] bg-primary text-on-primary border-border">'), []);
    assert.deepEqual(rules('resources/views/a.blade.php', '<div class="aspect-3/4 bg-surface-muted"></div>'), []);
});
