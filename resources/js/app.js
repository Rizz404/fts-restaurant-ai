// The only Alpine bootstrap. Register new modules in ui/register.js (or
// before start below); never call Alpine.start() anywhere else.
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

import { registerUi } from './ui/register.js';

Alpine.plugin(focus);
Alpine.plugin(collapse);

registerUi(Alpine);

window.Alpine = Alpine;

Alpine.start();
