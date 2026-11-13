/**
 * Design Library: toolbar button + full-screen pattern browser.
 *
 * Catalogue comes from this site (GET flx-blocks/v1/library) and from linked Flux libraries
 * (GET flx-blocks/v1/library/remote, fetched server side). Format: docs/LIBRARY.md.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { createRoot, useState, useEffect, useMemo, useRef } from '@wordpress/element';
import { Modal, Button, SearchControl, Spinner, Notice, TextControl, ToggleControl, Icon } from '@wordpress/components';
import { BlockPreview, store as blockEditorStore } from '@wordpress/block-editor';
import { parse } from '@wordpress/blocks';
import { dispatch, select } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { showSettings } from '@flx-blocks/helpers';
import { flxbkLogo } from '../../style-library/icons/flxbk-logo';
import './editor.scss';

const LOCAL = 'local';

function useCatalogue(source, refreshKey) {
	const [state, setState] = useState({ loading: true, error: '', data: null });
	useEffect(() => {
		let live = true;
		setState({ loading: true, error: '', data: null });
		const path =
			LOCAL === source
				? '/flx-blocks/v1/library'
				: `/flx-blocks/v1/library/remote?source=${encodeURIComponent(source)}${refreshKey ? '&refresh=1' : ''}`;
		apiFetch({ path })
			.then((data) => live && setState({ loading: false, error: '', data }))
			.catch((e) => live && setState({ loading: false, error: e?.message || __('Could not load the library.', 'flx-blocks'), data: null }));
		return () => {
			live = false;
		};
	}, [source, refreshKey]);
	return state;
}

function LazyPreview({ item }) {
	const ref = useRef();
	const [visible, setVisible] = useState(false);
	useEffect(() => {
		if (!ref.current || visible) {
			return;
		}
		const io = new window.IntersectionObserver(
			(entries) => {
				if (entries.some((e) => e.isIntersecting)) {
					setVisible(true);
					io.disconnect();
				}
			},
			{ rootMargin: '300px' }
		);
		io.observe(ref.current);
		return () => io.disconnect();
	}, [visible]);
	const blocks = useMemo(() => (visible ? parse(item.content) : []), [visible, item.content]);
	return (
		<div ref={ref} className="flxbk-dl-preview">
			{visible ? (
				<BlockPreview blocks={blocks} viewportWidth={item.viewportWidth || 1200} />
			) : (
				<Spinner />
			)}
		</div>
	);
}

function insertItem(item, onDone) {
	const blocks = parse(item.content);
	if (!blocks.length) {
		return;
	}
	const be = select(blockEditorStore);
	const point = be.getBlockInsertionPoint ? be.getBlockInsertionPoint() : {};
	const selected = be.getSelectedBlockClientId();
	let rootClientId = point?.rootClientId;
	let index = point?.index;
	if (selected && undefined === index) {
		rootClientId = be.getBlockRootClientId(selected);
		index = be.getBlockIndex(selected) + 1;
	}
	dispatch(blockEditorStore).insertBlocks(blocks, index, rootClientId || undefined);
	dispatch(noticesStore).createSuccessNotice(sprintf(__('“%s” added.', 'flx-blocks'), item.title), {
		type: 'snackbar',
	});
	onDone();
}

function Catalogue({ source, onClose }) {
	const [refreshKey, setRefreshKey] = useState(0);
	const { loading, error, data } = useCatalogue(source, refreshKey);
	const [category, setCategory] = useState('');
	const [search, setSearch] = useState('');

	const items = useMemo(() => {
		if (!data?.items) {
			return [];
		}
		const q = search.trim().toLocaleLowerCase();
		return data.items.filter((item) => {
			if (category && !(item.categories || []).includes(category)) {
				return false;
			}
			if (!q) {
				return true;
			}
			return [item.title, item.description, ...(item.keywords || [])].join(' ').toLocaleLowerCase().includes(q);
		});
	}, [data, category, search]);

	const counts = useMemo(() => {
		const c = {};
		(data?.items || []).forEach((item) => (item.categories || []).forEach((cat) => (c[cat] = (c[cat] || 0) + 1)));
		return c;
	}, [data]);

	if (loading) {
		return (
			<div className="flxbk-dl-empty">
				<Spinner />
			</div>
		);
	}
	if (error) {
		return (
			<div className="flxbk-dl-empty">
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
				{LOCAL !== source && (
					<Button variant="secondary" onClick={() => setRefreshKey(Date.now())}>
						{__('Try again', 'flx-blocks')}
					</Button>
				)}
			</div>
		);
	}

	return (
		<div className="flxbk-dl-body">
			<nav className="flxbk-dl-cats" aria-label={__('Categories', 'flx-blocks')}>
				<SearchControl
					__nextHasNoMarginBottom
					value={search}
					onChange={setSearch}
					placeholder={__('Search patterns', 'flx-blocks')}
				/>
				<Button className="flxbk-dl-cat" isPressed={'' === category} onClick={() => setCategory('')}>
					<span>{__('All', 'flx-blocks')}</span>
					<span className="flxbk-dl-count">{data.items.length}</span>
				</Button>
				{(data.categories || [])
					.filter((cat) => counts[cat.slug])
					.map((cat) => (
						<Button
							key={cat.slug}
							className="flxbk-dl-cat"
							isPressed={category === cat.slug}
							onClick={() => setCategory(cat.slug)}
						>
							<span>{cat.label}</span>
							<span className="flxbk-dl-count">{counts[cat.slug]}</span>
						</Button>
					))}
				{LOCAL !== source && (
					<Button variant="link" className="flxbk-dl-refresh" onClick={() => setRefreshKey(Date.now())}>
						{__('Refresh library', 'flx-blocks')}
					</Button>
				)}
			</nav>
			<div className="flxbk-dl-grid-wrap">
				{!items.length ? (
					<div className="flxbk-dl-empty">
						<p>
							{data.items.length
								? __('No pattern matches your search.', 'flx-blocks')
								: __('This library has no patterns yet. Theme and preset patterns, and the patterns you save, appear here.', 'flx-blocks')}
						</p>
					</div>
				) : (
					<>
						<p className="flxbk-dl-total">
							{sprintf(_n('%d pattern', '%d patterns', items.length, 'flx-blocks'), items.length)}
						</p>
						<div className="flxbk-dl-grid">
							{items.map((item) => (
								<button
									type="button"
									key={item.id}
									className="flxbk-dl-card"
									onClick={() => insertItem(item, onClose)}
									title={__('Insert', 'flx-blocks')}
								>
									<LazyPreview item={item} />
									<span className="flxbk-dl-card-title">{item.title}</span>
								</button>
							))}
						</div>
					</>
				)}
			</div>
		</div>
	);
}

function Manage({ sources, canManage, onChanged }) {
	const [form, setForm] = useState({ name: '', url: '', key: '' });
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState('');
	const [share, setShare] = useState(null);

	useEffect(() => {
		if (canManage) {
			apiFetch({ path: '/flx-blocks/v1/library/share' }).then(setShare).catch(() => {});
		}
	}, [canManage]);

	const add = () => {
		setBusy(true);
		setError('');
		apiFetch({ path: '/flx-blocks/v1/library/sources', method: 'POST', data: { action: 'add', ...form } })
			.then((res) => {
				setForm({ name: '', url: '', key: '' });
				onChanged(res.sources);
			})
			.catch((e) => setError(e?.message || __('The library could not be added.', 'flx-blocks')))
			.finally(() => setBusy(false));
	};
	const remove = (id) =>
		apiFetch({ path: '/flx-blocks/v1/library/sources', method: 'POST', data: { action: 'remove', id } }).then((res) =>
			onChanged(res.sources)
		);
	const saveShare = (data) =>
		apiFetch({ path: '/flx-blocks/v1/library/share', method: 'POST', data }).then(setShare).catch(() => {});

	if (!canManage) {
		return (
			<div className="flxbk-dl-manage">
				<p>{__('Only administrators can add libraries.', 'flx-blocks')}</p>
			</div>
		);
	}

	return (
		<div className="flxbk-dl-manage">
			<section>
				<h3>{__('Linked libraries', 'flx-blocks')}</h3>
				<p className="flxbk-dl-help">
					{__(
						'Add another Flux site by its address. Its sections and pages show up here as a library; nothing is copied until you insert it.',
						'flx-blocks'
					)}
				</p>
				{sources.length > 0 && (
					<ul className="flxbk-dl-sources">
						{sources.map((s) => (
							<li key={s.id}>
								<span>
									<strong>{s.name}</strong> <code>{s.url}</code>
									{s.has_key && <em> · {__('with key', 'flx-blocks')}</em>}
								</span>
								<Button variant="tertiary" isDestructive onClick={() => remove(s.id)}>
									{__('Remove', 'flx-blocks')}
								</Button>
							</li>
						))}
					</ul>
				)}
				<div className="flxbk-dl-add">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={__('Site address', 'flx-blocks')}
						placeholder="https://"
						type="url"
						value={form.url}
						onChange={(url) => setForm({ ...form, url })}
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={__('Name (optional)', 'flx-blocks')}
						value={form.name}
						onChange={(name) => setForm({ ...form, name })}
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={__('Key', 'flx-blocks')}
						value={form.key}
						onChange={(key) => setForm({ ...form, key })}
					/>
					<Button variant="primary" isBusy={busy} disabled={busy || !form.url} onClick={add}>
						{__('Add library', 'flx-blocks')}
					</Button>
				</div>
				{error && (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				)}
			</section>
			{share && (
				<section>
					<h3>{__('Share this site', 'flx-blocks')}</h3>
					<ToggleControl
						__nextHasNoMarginBottom
						label={__('Other Flux sites can use this site’s patterns as a library', 'flx-blocks')}
						checked={share.enabled}
						onChange={(enabled) => saveShare({ enabled })}
					/>
					{share.enabled && (
						<div className="flxbk-dl-share">
							<p>
								{__('Address', 'flx-blocks')}: <code>{share.url}</code>
							</p>
							<p>
								{__('Key', 'flx-blocks')}: <code>{share.key}</code>
							</p>
							<Button variant="secondary" onClick={() => saveShare({ regenerate: true })}>
								{__('New key', 'flx-blocks')}
							</Button>
							<p className="flxbk-dl-help">
								{__('A new key cuts off every site that used the old one.', 'flx-blocks')}
							</p>
						</div>
					)}
				</section>
			)}
		</div>
	);
}

function DesignLibraryModal({ onClose }) {
	const [tab, setTab] = useState(LOCAL);
	const [sources, setSources] = useState([]);
	const [canManage, setCanManage] = useState(false);

	useEffect(() => {
		apiFetch({ path: '/flx-blocks/v1/library/sources' })
			.then((res) => {
				setSources(res.sources || []);
				setCanManage(!!res.canManage);
			})
			.catch(() => {});
	}, []);

	return (
		<Modal
			className="flxbk-dl-modal"
			title={__('Design Library', 'flx-blocks')}
			icon={<Icon icon={flxbkLogo} size={28} />}
			onRequestClose={onClose}
			isFullScreen
		>
			<div className="flxbk-dl-tabs" role="tablist">
				<Button role="tab" isPressed={LOCAL === tab} onClick={() => setTab(LOCAL)}>
					{__('This site', 'flx-blocks')}
				</Button>
				{sources.map((s) => (
					<Button role="tab" key={s.id} isPressed={s.id === tab} onClick={() => setTab(s.id)}>
						{s.name}
					</Button>
				))}
				<Button role="tab" icon="plus-alt2" isPressed={'manage' === tab} onClick={() => setTab('manage')}>
					{__('Add library', 'flx-blocks')}
				</Button>
			</div>
			{'manage' === tab ? (
				<Manage
					sources={sources}
					canManage={canManage}
					onChanged={(list) => {
						setSources(list);
					}}
				/>
			) : (
				<Catalogue key={tab} source={tab} onClose={onClose} />
			)}
		</Modal>
	);
}

function ToolbarButton() {
	const [open, setOpen] = useState(false);
	return (
		<>
			<Button className="flxbk-dl-toolbar-button" variant="secondary" onClick={() => setOpen(true)}>
				<Icon icon={flxbkLogo} size={20} />
				<span>{__('Design Library', 'flx-blocks')}</span>
			</Button>
			{open && <DesignLibraryModal onClose={() => setOpen(false)} />}
		</>
	);
}

const TOOLBAR_SELECTORS = ['.editor-document-tools__left', '.edit-post-header-toolbar__left', '.edit-post-header-toolbar'];

// One holder and one React root for the page's lifetime. When the header re-renders and drops the
// holder, the same (already rendered) node is put back. Creating a new root each time rendered the
// button a frame later, so it disappeared and came back: the "blinking" toolbar button.
let holder = null;

function mount() {
	const toolbar = TOOLBAR_SELECTORS.map((s) => document.querySelector(s)).find(Boolean);
	if (!toolbar) {
		return false;
	}
	if (!holder) {
		holder = document.createElement('div');
		holder.id = 'flxbk-design-library-button';
		holder.className = 'flxbk-dl-toolbar';
		createRoot(holder).render(<ToolbarButton />);
	}
	if (holder.parentNode !== toolbar) {
		toolbar.appendChild(holder);
	}
	return true;
}

domReady(() => {
	if (
		(typeof flxbk_params !== 'undefined' && flxbk_params.showDesignLibrary === false) ||
		!showSettings('show', 'designlibrary')
	) {
		return;
	}
	// The header renders late and re-renders (zoom out, distraction free, device preview); a
	// MutationObserver puts the button back after React has committed, which a store
	// subscription cannot (it fires before the commit).
	mount();
	new window.MutationObserver(() => {
		if (!holder || !holder.isConnected) {
			mount();
		}
	}).observe(document.body, { childList: true, subtree: true });
});
