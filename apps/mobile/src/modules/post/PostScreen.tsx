// Light editor for a new post. The block body stays in the web editor (plan §13).
import { useState } from 'react';
import { ScrollView } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';
import { postBody, postEditPath, useApi, useRenderer, type PostStatus, type WpPost } from '@fluxwp/runtime';
import { cleanHtml, RichEditor } from '@/ui/RichEditor';
import { Button, Field, Segmented, Text } from '@/ui/primitives';
import { useScreenTitle } from '../kit';

export default function PostScreen(_props: { params: Record<string, string> }) {
  const { t } = useTranslation();
  useScreenTitle(t('mobile.screens.post.title'));
  const api = useApi();
  const renderer = useRenderer();
  const [title, setTitle] = useState('');
  const [excerpt, setExcerpt] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState<PostStatus>('draft');
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState<WpPost | null>(null);
  const [error, setError] = useState('');

  const save = async () => {
    if (!title.trim()) return;
    setBusy(true);
    setError('');
    try {
      const post = await api<WpPost>(saved ? `wp/v2/posts/${saved.id}` : 'wp/v2/posts', { method: 'POST', body: postBody({ title, excerpt, status, content: cleanHtml(content) }) });
      setSaved(post);
      renderer.ui.toast(t('mobile.screens.post.saved'), 'ok');
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <ScrollView testID="screen-post" contentContainerStyle={styles.wrap} keyboardShouldPersistTaps="handled">
      <Field label={t('mobile.screens.post.name')} value={title} onChangeText={setTitle} />
      <RichEditor value={content} onChange={setContent} />
      <Field label={t('mobile.screens.post.excerpt')} value={excerpt} onChangeText={setExcerpt} multiline />
      <Text variant="label">{t('mobile.screens.post.status')}</Text>
      <Segmented
        value={status}
        onChange={setStatus}
        options={[
          { value: 'draft', label: t('mobile.screens.post.draft') },
          { value: 'pending', label: t('mobile.screens.post.pending') },
          { value: 'publish', label: t('mobile.screens.post.publish') },
        ]}
      />
      {error ? <Text tone="danger">{error}</Text> : null}
      <Button label={t('mobile.screens.post.save')} onPress={() => void save()} loading={busy} disabled={!title.trim()} full />
      {saved ? <Button kind="secondary" icon="external" label={t('mobile.screens.post.blocks')} onPress={() => renderer.nav.push({ kind: 'web', path: postEditPath(saved.id) })} full /> : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { padding: theme.space[4], gap: theme.space[3], paddingBottom: theme.space[12] },
}));
