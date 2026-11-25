#!/usr/bin/env python3
"""React sohbet ekranının stilini (packages/modules/src/whatsapp/flxc.css) tools/app.css'ten üretir.

Tek kaynak app.css (klasik WhatsApp görünümü): tasarım değişikliği orada yapılır, sonra bu betik çalıştırılır.
- html.flxc-lock sayfa kilidi kuralları çıkarılır (wp-admin'de admin.php verir).
- Sonuna yalnız React ekranına özgü kurallar eklenir (FluxApp içinde sabit değil, kutu içinde).
Çalıştırma: python3 plugins/flx-chat/tools/gen-react-css.py
"""
import os

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(os.path.dirname(os.path.dirname(HERE)))
src = open(os.path.join(HERE, 'app.css'), encoding='utf-8').read().split('\n')
out = [l for l in src if not l.strip().startswith('html.flxc-lock')]
head = "/* Otomatik üretildi: plugins/flx-chat/tools/gen-react-css.py, kaynak plugins/flx-chat/tools/app.css. Elle değiştirmeyin. */\n"
extra = """

/* ---------------- Yalnız React ekranı ---------------- */
#flxc-app.is-embed { position: relative; inset: auto; z-index: auto; height: 100%; min-height: 0; }
"""
open(os.path.join(REPO, 'packages/modules/src/whatsapp/flxc.css'), 'w', encoding='utf-8').write(head + '\n'.join(out).rstrip('\n') + extra)
print('ok', len(out))
