/**
 * Flux Forms blocks: a form container and its field blocks.
 */
import { registerFields } from './fields';
import { registerForm } from './form';
import { registerLegacy } from './legacy';
import './editor.scss';

registerFields();
registerForm();
registerLegacy();
