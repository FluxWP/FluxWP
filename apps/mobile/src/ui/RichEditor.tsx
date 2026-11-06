// Full-screen rich text editor. The document is HTML edited in a web view (bold, lists,
// quote, link, images). Images upload to the site media library, then drop into the text.
import { useMemo, useRef, useState, type ComponentRef } from 'react';
import { ActivityIndicator, Modal, Pressable, TextInput, View } from 'react-native';
import { WebView, type WebViewMessageEvent } from 'react-native-webview';
import * as ImagePicker from 'expo-image-picker';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { uploadPickedMedia, useApi, type MediaFile } from '@fluxwp/runtime';
import { Button, Text } from './primitives';

export function cleanHtml(html: string): string {
  return html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '').replace(/\son\w+=("[^"]*"|'[^']*')/gi, '');
}

function documentHtml(opts: { html: string; placeholder: string; bg: string; fg: string; muted: string; accent: string; font: string }) {
  const initial = JSON.stringify(opts.html);
  return `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<style>
  html,body{margin:0;background:${opts.bg};color:${opts.fg};font-family:${opts.font};font-size:17px;line-height:1.55}
  #ed{min-height:100vh;box-sizing:border-box;padding:16px 16px 48px;outline:none}
  #ed:empty:before{content:attr(data-ph);color:${opts.muted}}
  img{max-width:100%;height:auto;border-radius:12px;display:block;margin:8px 0}
  blockquote{margin:8px 0;padding-left:12px;border-left:3px solid ${opts.accent}}
  h2{font-size:22px;margin:12px 0 6px} h3{font-size:18px;margin:10px 0 4px}
  a{color:${opts.accent}}
</style></head><body>
<div id="ed" contenteditable="true" data-ph="${opts.placeholder.replace(/"/g, '')}"></div>
<script>
  var ed=document.getElementById('ed');
  ed.innerHTML=${initial};
  function send(){var m=JSON.stringify({type:'html',html:ed.innerHTML}); if(window.ReactNativeWebView) window.ReactNativeWebView.postMessage(m);}
  ed.addEventListener('input', send);
  function onMsg(e){var d; try{d=JSON.parse(e.data)}catch(err){return}
    if(d.type==='cmd') document.execCommand(d.cmd,false,d.arg||null);
    if(d.type==='html') document.execCommand('insertHTML',false,d.html);
    if(d.type==='block') document.execCommand('formatBlock',false,d.tag);
    send();
  }
  document.addEventListener('message', onMsg);
  window.addEventListener('message', onMsg);
</script></body></html>`;
}

export function RichEditor({ value, onChange, upload = true }: { value: string; onChange: (html: string) => void; upload?: boolean }) {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const insets = useSafeAreaInsets();
  const api = useApi();
  const web = useRef<ComponentRef<typeof WebView>>(null);
  const [full, setFull] = useState(false);
  const [busy, setBusy] = useState(false);
  const [link, setLink] = useState(false);
  const [href, setHref] = useState('https://');

  const html = useMemo(
    () => documentHtml({
      html: value,
      placeholder: t('mobile.editor.placeholder'),
      bg: theme.colors.surface,
      fg: theme.colors.text,
      muted: theme.colors.textMuted,
      accent: theme.colors.primary,
      font: theme.font.family,
    }),
    // Rebuild only when the editor opens or the theme changes. Typing must not reset the document.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [full, theme.mode, theme.colors.surface, theme.colors.text],
  );

  const send = (payload: object) => web.current?.postMessage(JSON.stringify(payload));
  const onMessage = (e: WebViewMessageEvent) => {
    try {
      const msg = JSON.parse(e.nativeEvent.data) as { type?: string; html?: string };
      if (msg.type === 'html' && typeof msg.html === 'string') onChange(cleanHtml(msg.html));
    } catch {
      /* ignore malformed messages */
    }
  };

  const addImage = async () => {
    const picked = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85 });
    const asset = picked.assets?.[0];
    if (picked.canceled || !asset) return;
    setBusy(true);
    try {
      const file: MediaFile = { uri: asset.uri, name: asset.fileName || 'image.jpg', type: asset.mimeType || 'image/jpeg' };
      const media = await uploadPickedMedia({ api, file });
      if (media.source_url) send({ type: 'html', html: `<img src="${media.source_url}" alt="">` });
    } finally {
      setBusy(false);
    }
  };

  const tools: { id: string; label: string; message?: object }[] = [
    { id: 'b', label: 'B', message: { type: 'cmd', cmd: 'bold' } },
    { id: 'i', label: 'I', message: { type: 'cmd', cmd: 'italic' } },
    { id: 'u', label: 'U', message: { type: 'cmd', cmd: 'underline' } },
    { id: 'h', label: 'H', message: { type: 'block', tag: 'H2' } },
    { id: 'ul', label: '•', message: { type: 'cmd', cmd: 'insertUnorderedList' } },
    { id: 'ol', label: '1.', message: { type: 'cmd', cmd: 'insertOrderedList' } },
    { id: 'q', label: '❝', message: { type: 'block', tag: 'BLOCKQUOTE' } },
    { id: 'link', label: t('mobile.editor.link') },
    ...(upload ? [{ id: 'img', label: t('mobile.editor.image') }] : []),
  ];

  const body = (
    <View style={[styles.wrap, full && styles.full, full && { paddingTop: insets.top }]}>
      <View style={styles.bar}>
        <Text variant="label" style={styles.flex}>{t('mobile.editor.title')}</Text>
        <Pressable accessibilityRole="button" onPress={() => setFull((v) => !v)} hitSlop={8}>
          <Text tone="primary">{full ? t('mobile.editor.done') : t('mobile.editor.expand')}</Text>
        </Pressable>
      </View>
      <View style={styles.tools}>
        {tools.map((tool) => (
          <Pressable key={tool.id} accessibilityRole="button" accessibilityLabel={tool.label} onPress={() => {
              if (tool.id === 'img') void addImage();
              else if (tool.id === 'link') setLink(true);
              else if (tool.message) send(tool.message);
            }} style={styles.tool}>
            <Text style={tool.id === 'i' ? styles.italic : tool.id === 'b' ? styles.bold : undefined}>{tool.label}</Text>
          </Pressable>
        ))}
        {busy ? <ActivityIndicator color={theme.colors.primary} /> : null}
      </View>
      {link ? (
        <View style={styles.linkRow}>
          <TextInput value={href} onChangeText={setHref} autoCapitalize="none" autoCorrect={false} keyboardType="url" placeholder="https://" placeholderTextColor={theme.colors.textMuted} style={styles.linkInput} accessibilityLabel={t('mobile.editor.link')} />
          <Button
            label={t('mobile.editor.insert')}
            onPress={() => {
              send({ type: 'cmd', cmd: 'createLink', arg: href });
              setLink(false);
            }}
          />
        </View>
      ) : null}
      <WebView
        ref={web}
        originWhitelist={['*']}
        source={{ html }}
        onMessage={onMessage}
        style={styles.web}
      />
    </View>
  );
  if (!full) return body;
  return (
    <Modal visible animationType="none" presentationStyle="fullScreen" onRequestClose={() => setFull(false)}>
      {body}
    </Modal>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { minHeight: 280, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.lg, overflow: 'hidden', backgroundColor: theme.colors.surface },
  full: { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, zIndex: 30, borderRadius: 0, borderWidth: 0, minHeight: undefined },
  bar: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingVertical: 8, gap: 8 },
  flex: { flex: 1 },
  tools: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, paddingHorizontal: 8, paddingBottom: 8 },
  tool: { minWidth: 36, height: 36, paddingHorizontal: 8, borderRadius: theme.radius.md, borderWidth: 1, borderColor: theme.colors.border, alignItems: 'center', justifyContent: 'center', backgroundColor: theme.colors.bg },
  bold: { fontWeight: '800' },
  italic: { fontStyle: 'italic' },
  linkRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: 12, paddingBottom: 8 },
  linkInput: { flex: 1, minHeight: 40, borderWidth: 1, borderColor: theme.colors.border, borderRadius: theme.radius.md, paddingHorizontal: 10, color: theme.colors.text, backgroundColor: theme.colors.bg },
  web: { flex: 1, minHeight: 220, backgroundColor: theme.colors.surface },
}));
