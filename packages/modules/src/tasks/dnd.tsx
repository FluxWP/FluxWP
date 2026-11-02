// Sürükle-bırak (@dnd-kit/core): liste satırları, pano kartları, takvim günleri.
// Bırakılan bölge + bırakılan yerin öncesi/sonrası (görev kimlikleri) onDrop'a verilir.
import { createContext, useContext, useRef, useState, type ReactNode } from 'react';
import { DndContext, DragOverlay, PointerSensor, TouchSensor, useDraggable, useDroppable, useSensor, useSensors, type DragEndEvent, type DragStartEvent } from '@dnd-kit/core';

export interface DropInfo {
  id: number;
  zone: string;
  /** Bırakılan yerin üstündeki öğe. */
  after: number | null;
  /** Bırakılan yerin altındaki öğe. */
  before: number | null;
  /** Bölgede görünür öğe sırası (DOM'dan). */
  items: HTMLElement[];
  index: number;
}

const DragCtx = createContext<{ active: number | null; canStart: (id: number) => boolean }>({ active: null, canStart: () => true });

export function DndArea({
  children,
  onDrop,
  canStart = () => true,
  overlay,
  onStart,
  onEnd,
}: {
  children: ReactNode;
  onDrop: (info: DropInfo) => void;
  canStart?: (id: number) => boolean;
  overlay?: (id: number) => ReactNode;
  onStart?: (id: number) => void;
  onEnd?: () => void;
}) {
  const [active, setActive] = useState<number | null>(null);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(TouchSensor, { activationConstraint: { delay: 350, tolerance: 8 } }));
  const swallow = () => {
    const stop = (ev: Event) => {
      ev.stopPropagation();
      ev.preventDefault();
    };
    window.addEventListener('click', stop, true);
    setTimeout(() => window.removeEventListener('click', stop, true), 60);
  };
  const start = (ev: DragStartEvent) => {
    setActive(Number(ev.active.id));
    onStart?.(Number(ev.active.id));
  };
  const end = (ev: DragEndEvent) => {
    const id = Number(ev.active.id);
    setActive(null);
    onEnd?.();
    swallow();
    if (!ev.over) return;
    const zone = String(ev.over.id);
    const zoneEl = document.querySelector<HTMLElement>(`[data-dnd-zone="${CSS.escape(zone)}"]`);
    const act = ev.activatorEvent as PointerEvent | TouchEvent;
    const startY = 'touches' in act ? (act.touches[0]?.clientY ?? 0) : act.clientY;
    const y = startY + ev.delta.y;
    const items = zoneEl ? [...zoneEl.querySelectorAll<HTMLElement>('[data-dnd-item]')].filter((x) => Number(x.dataset.dndItem) !== id && !x.classList.contains('is-drag-hidden')) : [];
    let index = items.length;
    for (let i = 0; i < items.length; i++) {
      const r = items[i]!.getBoundingClientRect();
      if (y < r.top + r.height / 2) {
        index = i;
        break;
      }
    }
    onDrop({
      id,
      zone,
      after: index > 0 ? Number(items[index - 1]!.dataset.dndItem) : null,
      before: index < items.length ? Number(items[index]!.dataset.dndItem) : null,
      items,
      index,
    });
  };
  return (
    <DragCtx.Provider value={{ active, canStart }}>
      <DndContext sensors={sensors} onDragStart={start} onDragEnd={end} onDragCancel={() => (setActive(null), onEnd?.())} autoScroll>
        {children}
        <DragOverlay dropAnimation={null}>{active !== null && overlay ? <div className="flxg-dnd-ghost">{overlay(active)}</div> : null}</DragOverlay>
      </DndContext>
    </DragCtx.Provider>
  );
}

/** Sürüklenebilir öğe: ref + dinleyiciler döner. */
export function useDragItem(id: number) {
  const ctx = useContext(DragCtx);
  const disabled = !ctx.canStart(id);
  const d = useDraggable({ id: String(id), disabled });
  return {
    ref: d.setNodeRef,
    props: { ...d.listeners, ...d.attributes, 'data-dnd-item': id, role: undefined, tabIndex: 0 } as Record<string, unknown>,
    dragging: ctx.active === id,
  };
}

/** Bırakma bölgesi. */
export function useDropZone(id: string, disabled = false) {
  const d = useDroppable({ id, disabled });
  const ref = useRef<HTMLElement | null>(null);
  return {
    ref: (el: HTMLElement | null) => {
      ref.current = el;
      d.setNodeRef(el);
    },
    props: { 'data-dnd-zone': id },
    over: d.isOver,
  };
}
