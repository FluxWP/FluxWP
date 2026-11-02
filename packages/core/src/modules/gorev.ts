// Flux Tasks (flx-tasks/v1/app/*). Sözleşme: docs/fluxapp/MODUL-API.md §2
import type { ApiClient } from '../api/client';
import type { Page } from './common';

export interface TaskPerson { id: number; name: string; avatar?: string }

export interface Task {
  id: number;
  title: string;
  status: 'open' | 'done';
  project: { id: number; title: string; color: string };
  list: { id: number; title: string };
  due: string | null;
  due_label: string;
  overdue: boolean;
  priority: 0 | 1 | 2 | 3;
  assignees: TaskPerson[];
  comments: number;
  checklist: { done: number; total: number };
  url: string;
}

export interface TaskComment { id: number; author: TaskPerson; text: string; at: string; at_label: string }

export interface TaskDetail extends Omit<Task, 'checklist' | 'comments'> {
  description_html: string;
  checklist: { id: number; text: string; done: boolean }[];
  comments: TaskComment[];
}

export interface TaskProject { id: number; title: string; color: string; open: number; lists: { id: number; title: string; status: 'open' | 'done' }[] }

export type TaskView = 'mine' | 'today' | 'overdue' | 'project';

export const gorev = (api: ApiClient) => ({
  projects: () => api<{ items: TaskProject[] }>('flx-tasks/v1/app/projects'),
  tasks: (p: { view: TaskView; project?: number; list?: number; q?: string; page?: number }) => api<Page<Task>>('flx-tasks/v1/app/tasks', { query: p }),
  task: (id: number) => api<TaskDetail>(`flx-tasks/v1/app/tasks/${id}`),
  create: (b: { title: string; project: number; list?: number; due?: string; assignees?: number[] }) => api<Task>('flx-tasks/v1/app/tasks', { method: 'POST', body: b }),
  update: (id: number, b: Partial<{ title: string; status: 'open' | 'done'; due: string | null; list: number; assignees: number[]; priority: number }>) =>
    api<Task>(`flx-tasks/v1/app/tasks/${id}`, { method: 'POST', body: b }),
  comment: (id: number, text: string) => api<TaskComment>(`flx-tasks/v1/app/tasks/${id}/comments`, { method: 'POST', body: { text } }),
  check: (id: number, item: number, done: boolean) => api<{ ok: boolean }>(`flx-tasks/v1/app/tasks/${id}/checklist/${item}`, { method: 'POST', body: { done } }),
});
