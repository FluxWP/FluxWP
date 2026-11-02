# WhatsApp (Flux Chat) ekranının ortak React'e taşınması

Hedef: tek React ekranı (`packages/modules/cagri`), hem FluxWP'te hem WP admin'de (eklenti bundle'ı yükler) aynı kod.
Kaynak: plugins/flx-chat/assets/js/app.js (~3000 satır) + includes/rest.php. Ekran tam REST'i kullanır (flx-chat/v1), /app özet uçlarını değil; Flux App belirteci her REST ucunda geçerli.

## Veri akışı
- GET /bootstrap → me{id,name,manage}, agents, labels, connections, replies, unread, ai{id,label,auto}|null, bot, records{donations,certificates,search,chips,types,templates,events,...}, rev.
- GET /sync?since=<rev-15000>&chat=<aktif>&after=<en büyük mesaj id> → {rev, chats(rev>since, ≤200), messages(aktif sohbet), incoming(≤20, bildirim), connections, unread, nonce}. Aralık: max(3, poll) sn; gizli sekmede ≥60 sn; bot aktifken ≤3 sn; hata geri çekilme ×2^n ≤120 sn. rev değişmeyen öğe atlanır.
- GET /chats?filter=all|unread|mine|unassigned|ai|closed&q&label&offset&limit=50; POST /chats {phone,name,conn,check}.
- GET/POST /chats/{id} (+sections: flxs/flxb kayıtları); POST alanları name, notes, labels[], status, assigned, pinned, unread.
- GET /chats/{id}/messages?before= (40'lık, {messages, more, note}); POST mesaj (JSON ya da multipart: type text|note|audio|image|video|document, body, quoted, source quick|ai, preview, file).
- read, typing{state}, avatar{force}, ai{fresh,hint}, bot{state}, usage, usage/reset, clear{confirm,keep_receipts,forget_memory}, unblock.
- /messages/{id}/retry, /react{emoji}, DELETE (not), /receipt.
- /media/{id} (çerez: _wpnonce; uygulamada belirteçle blob), /check-number, /link-preview, /replies(+use), /blocklist, /memory/*, /reminders, /records/*, /bot/limit.
- Dışa aktarma admin-post (flxc_export) → uygulama için REST karşılığı gerekir (Çağrı Merkezi thread'i).

## Özellik envanteri (app.js)
Kabuk: liste / sohbet / kişi paneli, bağlantı durumu + kopuk bandı, tema (auto/açık/koyu), vurgu rengi, ?chat= derin link, sekme başlığında okunmamış, toast, modal, lightbox, uzun basma menüsü, çevrimdışı/oturum bitti bandı.
Liste: arama (280 ms, ad/not/mesaj/numara), filtre sekmeleri, etiket çipleri, 50'lik sayfa + sonsuz kaydırma, sabitlenen üstte, satır: avatar, etiket noktaları, zaman (bugün/Dün/gün/tarih), önizleme (yazıyor/ses kaydediyor/Asistan yazıyor, tik, temsilci adı, tür simgesi), sabit/atanan/okunmamış rozetleri, yeni öğe parlaması. Satır menüsü: kişi bilgisi, sabitle, okunmadı yap, bana ata, bağış kaydı, dışa aktar, kapat/aç, asistan kullanımı, (yönetici) hafıza, hatırlatıcılar, kara liste, geçmişi temizle.
Yeni sohbet: numara + ad + hat, canlı numara kontrolü (geçersiz / WhatsApp var / yok / var olan sohbete git).
Başlık: geri, kişi paneli, alt satır (yazıyor/telefon/atanan/kapalı/kara liste/hat), asistan hapı, bağış (bekleyen sayısı), kayıtlar, ata menüsü, kapat/aç, ⋮.
Mesajlar: son 40 + yukarı kaydırınca eski, geçmiş uyarısı, aşağı in düğmesi (yeni sayısı), gün ayracı, 5 dk gruplama, sistem satırı, dahili not, kaynak etiketi (telefon/sertifika/kayıt/otomatik/ai/bot/api), grup göndericisi, alıntı (tıkla → orijinale atla), silindi rozeti, dekont çubuğu, tepkiler, başarısız + Tekrar dene. Türler: metin (WhatsApp biçimi *kalın* _italik_ ~üstü çizili~ ```kod```, link; 1-6 emoji büyük), görsel/çıkartma (oran, lightbox), video, ses (oynat/sar/hız 1-1.5-2×, tek seferde bir), belge, konum, kişi kartı (mesaj gönder), silinmiş. Meta: düzenlendi, saat, tikler (pending/sent/delivered/read/played/failed).
Mesaj menüsü: 6 hızlı tepki + emoji seçici, yanıtla, kopyala, indir, yeniden gönder, dekont olarak eşleştir, notu sil, bilgi. Okundu yalnız görünür + altta iken.
Link önizleme: balonda tembel yükleme (en çok 2 eşzamanlı, önbellek), yazarken 700 ms sonra kart + kaldır.
Yazma alanı: emoji, ek (+: foto/video, belge, hazır cevap, dahili not, AI öneri), mikrofon, taslak (sohbet başına), otomatik büyüme, Enter gönder (dokunmatikte değil), yazıyor sinyali (4.5 sn), not modu, alıntı çubuğu, iyimser gönderim, ilk temsilci mesajı sohbeti kendine atar, yapıştır/sürükle-bırak + başlıklı önizleme, ses kaydı (ogg/opus > webm > mp4, <0.7 sn atılır), hazır cevap çipleri (en çok kullanılan 14).
Hazır cevaplar: "/" seçici (kısayol/başlık/metin, ok tuşları), değişken doldurma (/use), yönetici ekranı (kategori, değişken çipleri {first_name} {name} {phone} {agent} {brand} {site} {date}).
AI: öneri çubuğu (gönder/düzenle/yenile/ipucu), otomatik öneri koşulları, asistan hapı (açık/duraklatıldı/kapalı), kullanım penceresi (bugün/limit/7 gün/maliyet, global limit).
Kişi paneli: ad düzenle, push adı, telefon (kopyala/ara), etiketler, atanan, notlar (900 ms otomatik kayıt), bağış/sertifika oluştur, eklenti bölümleri (sertifikalar, bağışlar), sabitle, kapat, ilk görülme.
Kayıtlar (Flux Donate/Sertifika varsa): dekont çipi + AI kontrol rozeti, bağış penceresi (tür, seçenek, adet, tutar, yakınlar + vekalet, durum, yöntem, referans, bağışçı, not, sertifika, mesaj şablonu), sertifika penceresi (şablon alanları, etkinlik, ısıt + gönder), dekont eşleştir, kayıt arama paneli, kişi kayıtları.
Yönetici: dışa aktar (md/json, maske, notlar, tek/toplu), geçmişi temizle ("TEMİZLE"), kişi hafızası (ekle/düzenle/özetle/sil), kara liste (kişi/genel), hatırlatıcılar (duraklat/sil/sohbete git).
Bildirim: ses + masaüstü bildirimi (gizli sekme), tıklayınca sohbet.
Kısayollar: Enter/Shift+Enter, Esc sırası (menü → açılır → alıntı → not), "/" seçicide ↑↓ Enter Tab, çift tık alıntı, yapıştırınca ek.
Mobil: <900 px liste/sohbet görünümü, geçmiş (geri tuşu), 900-1279 kişi paneli üstte.
Bilinen hata: app.js B.memHours okuyor ama admin.php vermiyor (hep 24).

## Aşamalar
Durum (2026-11-01): 1 ve 2 tamam (packages/modules/src/whatsapp). 2'de kalan: hızlı cevap yönetici ekranı, uzun basma menüsü, ?chat= derin link, tam emoji seçici.

1. Altyapı: packages/ui (ortak bileşenler), modül host sözleşmesi (api, gezinme, bildirim, tema), apps/wp-embed (WP admin'de mount, çerez + nonce).
2. WhatsApp çekirdek: bootstrap + sync, liste, sohbet, tüm mesaj türleri, yazma alanı (ek, ses, emoji, not, alıntı), tepkiler, hazır cevaplar, AI öneri, asistan, kişi paneli, bildirim, mobil düzen.
3. Kayıtlar: bağış/sertifika/dekont pencereleri, kayıt arama.
4. Yönetici araçları: dışa aktar (REST karşılığı), temizle, hafıza, kara liste, hatırlatıcılar, kullanım.
5. Eklentide geçiş: Çağrı Merkezi admin sayfası bundle'ı yükler (ayar: yeni arayüz), eşitlik doğrulanınca eski app.js kaldırılır.
