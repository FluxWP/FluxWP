// Built-in `core` module (WordPress REST): used when the site does not send its own `core` manifest
// (Flux App before the manifest endpoint, or limited mode without Flux App, plan §4.3).
// Labels are "$t:<key>" app strings; a server manifest sends translated text instead.
import type { Badge, Manifest } from './types/manifest';

const postStatus: Badge[] = [
  { text: '$t:shell.status.publish', tone: 'ok', when: { path: 'status', op: 'eq', value: 'publish' } },
  { text: '$t:shell.status.draft', tone: 'muted', when: { path: 'status', op: 'eq', value: 'draft' } },
  { text: '$t:shell.status.pending', tone: 'warn', when: { path: 'status', op: 'eq', value: 'pending' } },
  { text: '$t:shell.status.future', tone: 'info', when: { path: 'status', op: 'eq', value: 'future' } },
  { text: '$t:shell.status.private', tone: 'muted', when: { path: 'status', op: 'eq', value: 'private' } },
];

const ALL = 'publish,draft,pending,future,private';
const wpPaging = { mode: 'wp' as const, param: 'page', size_param: 'per_page', size: 20 };

export const CORE_MANIFEST: Manifest = {
  schema: 'flx-app-manifest/1',
  id: 'core',
  plugin: 'wordpress',
  title: 'WordPress',
  icon: 'dashboard',
  entry: 'dashboard',
  fallback: { web: 'index.php' },
  routes: {
    'post.php?post={id}&action=edit': { screen: 'post', params: { id: '{id}' } },
    'edit.php': { screen: 'posts' },
    'edit.php?post_type=page': { screen: 'pages' },
    'edit-comments.php': { screen: 'comments' },
  },
  sources: {
    posts: { path: 'wp/v2/posts', query: { context: 'edit', status: ALL, _fields: 'id,title,status,date,author,link' }, paging: wpPaging, key: 'id', cache: { stale: 60, persist: true } },
    post: { path: 'wp/v2/posts/{{params.id}}', query: { context: 'edit' }, cache: { stale: 30 } },
    pages: { path: 'wp/v2/pages', query: { context: 'edit', status: ALL, _fields: 'id,title,status,date,link' }, paging: wpPaging, key: 'id', cache: { stale: 60, persist: true } },
    comments: { path: 'wp/v2/comments', query: { context: 'edit', status: 'all', _fields: 'id,author_name,content,date,status,post' }, paging: wpPaging, key: 'id', cache: { stale: 30, persist: true } },
  },
  screens: {
    dashboard: { type: 'native', component: 'dashboard', title: '$t:shell.tabs.home', fallback: { web: 'index.php' } },
    posts: {
      type: 'list',
      title: '$t:shell.tabs.posts',
      source: 'posts',
      search: { param: 'search', placeholder: '$t:core.searchPosts' },
      filters: [
        {
          param: 'status',
          label: '$t:core.status',
          type: 'segmented',
          options: [
            { value: '', label: '$t:core.statusAll' },
            { value: 'draft', label: '$t:core.statusDraft' },
            { value: 'pending', label: '$t:core.statusPending' },
          ],
        },
      ],
      item: { title: '{{title.rendered}}', meta: '{{date|date}}', badges: postStatus },
      onPress: { target: 'screen:core/post', params: { id: '{{id}}' } },
      actions: { header: ['new_post'], fab: 'new_post' },
      empty: { title: '$t:core.emptyPosts' },
    },
    post: {
      type: 'detail',
      title: '$t:core.post',
      source: 'post',
      header: { title: '{{title.rendered}}', subtitle: '{{date|datetime}}' },
      sections: [
        {
          type: 'fields',
          title: '$t:core.details',
          fields: [
            ...(['publish', 'draft', 'pending', 'future', 'private'] as const).map((st) => ({ label: '$t:core.status', value: `$t:shell.status.${st}`, when: { path: 'status', op: 'eq' as const, value: st } })),
            { label: '$t:core.link', value: '{{link}}', link: '{{link}}', copy: true, when: { path: 'link', op: 'filled' } },
          ],
        },
        { type: 'content', title: '$t:core.excerpt', value: '{{excerpt.rendered}}', format: 'html', when: { path: 'excerpt.rendered', op: 'filled' } },
      ],
      actions: { header: ['edit_post', 'web_post', 'trash_post'] },
    },
    post_form: {
      type: 'form',
      title: '$t:core.post',
      load: 'post',
      fields: [
        { name: 'title', type: 'text', label: '$t:core.title', required: true, maxLength: 200 },
        {
          name: 'status',
          type: 'segmented',
          label: '$t:core.status',
          default: 'draft',
          options: [
            { value: 'publish', label: '$t:shell.status.publish' },
            { value: 'draft', label: '$t:shell.status.draft' },
            { value: 'pending', label: '$t:shell.status.pending' },
          ],
        },
        { name: 'excerpt', type: 'textarea', label: '$t:core.excerpt' },
      ],
      submit: { method: 'POST', path: 'wp/v2/posts', path_create: 'wp/v2/posts', path_update: 'wp/v2/posts/{{params.id}}' },
      after: { toast: '$t:form.saved', back: true, invalidate: ['posts', 'post'] },
    },
    pages: {
      type: 'list',
      title: '$t:core.pages',
      source: 'pages',
      search: { param: 'search' },
      item: { title: '{{title.rendered}}', meta: '{{date|date}}', badges: postStatus },
      onPress: { target: 'web:post.php?post={{id}}&action=edit' },
    },
    comments: {
      type: 'list',
      title: '$t:shell.tabs.comments',
      source: 'comments',
      search: { param: 'search', placeholder: '$t:core.searchComments' },
      filters: [
        {
          param: 'status',
          label: '$t:core.status',
          type: 'segmented',
          options: [
            { value: 'all', label: '$t:core.statusAll' },
            { value: 'hold', label: '$t:core.statusHold' },
          ],
        },
      ],
      item: {
        title: '{{author_name}}',
        subtitle: '{{content.rendered|truncate:140}}',
        meta: '{{date|ago}}',
        avatar: { text: '{{author_name}}' },
        badges: [{ text: '$t:core.statusHold', tone: 'warn', when: { path: 'status', op: 'eq', value: 'hold' } }],
        unread: { path: 'status', op: 'eq', value: 'hold' },
      },
      actions: { row: ['approve_comment', 'spam_comment', 'trash_comment'] },
      empty: { title: '$t:core.emptyComments' },
    },
  },
  actions: {
    new_post: { label: '$t:core.newPost', icon: 'plus', navigate: { target: 'screen:core/post_form' } },
    edit_post: { label: '$t:core.edit', icon: 'edit', navigate: { target: 'screen:core/post_form', params: { id: '{{data.id}}' } } },
    web_post: { label: '$t:core.openInPanel', icon: 'globe', web: 'post.php?post={{data.id}}&action=edit' },
    trash_post: {
      label: '$t:core.trash',
      icon: 'trash',
      tone: 'danger',
      confirm: { title: '$t:core.trashConfirm', ok: '$t:core.trash' },
      request: { method: 'DELETE', path: 'wp/v2/posts/{{data.id}}' },
      after: { toast: '$t:core.trashed', back: true, invalidate: ['posts'] },
      haptic: 'warning',
    },
    approve_comment: {
      label: '$t:core.approve',
      icon: 'check',
      tone: 'accent',
      when: { path: 'status', op: 'eq', value: 'hold' },
      request: { method: 'POST', path: 'wp/v2/comments/{{item.id}}', body: { status: 'approved' } },
      after: { toast: '$t:core.approved', refresh: true },
    },
    spam_comment: { label: '$t:core.spam', icon: 'x', request: { method: 'POST', path: 'wp/v2/comments/{{item.id}}', body: { status: 'spam' } }, after: { refresh: true } },
    trash_comment: {
      label: '$t:core.trash',
      icon: 'trash',
      tone: 'danger',
      confirm: { title: '$t:core.trashConfirm', ok: '$t:core.trash' },
      request: { method: 'DELETE', path: 'wp/v2/comments/{{item.id}}' },
      after: { toast: '$t:core.trashed', refresh: true },
    },
  },
};
