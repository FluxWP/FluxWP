// Shapes of the chat REST protocol (plugins/flx-ai/includes/rest.php, chats.php). The same protocol serves the
// floating chat, the FluxApp embed and this screen; turns stream as NDJSON, so they stay outside GraphQL.

export interface ToolCall {
  id: string;
  type?: string;
  function: { name: string; arguments: string };
}

export interface ChatFile {
  key: string;
  name: string;
  type?: string;
}

export interface Msg {
  role: 'user' | 'assistant' | 'tool' | 'system';
  content?: string | null;
  tool_calls?: ToolCall[];
  tool_call_id?: string;
  _id?: string;
  _ts?: number;
  /** Text an add-on wants shown instead of the stored one (e.g. an agent report). */
  _show?: string | null;
  _thinking?: string;
  _ms?: number;
  _ok?: boolean;
  _name?: string;
  _error?: string;
  _rejected?: boolean;
  _files?: ChatFile[];
  _task?: { title?: string };
  /** Client only: the assistant message being streamed. */
  _live?: number;
}

export interface Pending {
  id: string;
  name: string;
  title: string;
  source: string;
  args: Record<string, unknown>;
}

export interface ChatData {
  id: number;
  title: string;
  archived: number;
  status: string;
  updated: number;
  model: string;
  since: number;
  total: number;
  messages: Msg[];
  pending: Pending[];
  turn?: unknown;
}

export interface Command {
  id: string;
  group?: string;
  groupLabel?: string;
  kind: 'action' | 'insert' | 'skill';
  action?: string;
  insert?: string;
  slug?: string;
  title: string;
  desc?: string;
}

export interface Boot {
  tools: Record<string, { title: string; write: boolean }>;
  ready: boolean;
  model: string;
  modelLabel: string;
  canManage: boolean;
  commands: Command[];
  maxBytes: number;
  stt?: boolean;
  widgets: boolean;
  mermaid: string;
  suggestions: string[];
  cm?: { js: string; css: string };
  view?: { url: string; nonce: string };
}

export interface WsItem {
  name: string;
  path: string;
  type: 'dir' | 'file';
  size: number;
  ago: string;
  count: number | null;
}

export interface ChangeFile {
  path: string;
  op: 'add' | 'edit' | 'delete';
  add: number;
  del: number;
  state: string;
  big: boolean;
  before?: string | null;
  after?: string | null;
}

export interface Change {
  id: number;
  kind: 'pr' | 'commit';
  status: string;
  title: string;
  chat_id: number;
  task_id: number;
  created: number;
  ago: string;
  date: string;
  files: ChangeFile[];
}

export interface WsState {
  ready: boolean;
  dir: string;
  access: 'workspace' | 'site';
  folders: string[];
  task: number;
  skills: string;
  files: string;
  changes: Change[];
  exists?: boolean;
  items?: WsItem[];
}

export interface WsFile {
  path: string;
  content: string;
  size: number;
  ago: string;
  files: string;
  editable: boolean;
}

export interface DecideResult {
  change: Change;
  conflicts?: string[];
  errors?: Record<string, string>;
}

export type StepState = 'continue' | 'busy' | 'approval' | 'done';

export interface StepResult {
  state: StepState;
  chat?: ChatData;
}

/** One line of the step stream. */
export type LiveEvent =
  | { t: 'chat'; chat: ChatData }
  | { t: 'model' }
  | { t: 'text' | 'think'; d?: string }
  | { t: 'tool'; id?: string; name?: string };

export interface ModelInfo {
  id: string;
  name: string;
  family: string;
  ctx: number;
  in: number;
  out: number;
  /** Runs on Flux Cloud credits and the plan does not include it. */
  locked?: boolean;
}

export type Decisions = Record<string, 'approve' | 'reject'>;

export class ChatError extends Error {
  constructor(
    message: string,
    public code = '',
    public status = 0,
    public net = false,
  ) {
    super(message);
  }
}
