SLUG='flx-chat'; DIR=__import__('os').path.join(__import__('os').path.dirname(__import__('os').path.abspath(__file__)),'../../../')+'plugins/flx-chat'; WP='6.4'; PHP='7.4'; DOCS='cagri-merkezi'; API=False
C={
'en':dict(name='Flux Call Center',
 tagline='A WhatsApp inbox inside WordPress, with an AI assistant that takes donations.',
 desc='Flux Call Center shows every chat of your WhatsApp line live in the admin: incoming and outgoing messages, ones written on the phone and certificate messages. The team answers with quick replies, labels, assignment and internal notes; donations (Flux Donation) and certificates (Flux Certificate) are recorded from the chat, and an AI assistant can talk to donors, read bank receipts and hand off to a person when needed.',
 badges=['![WhatsApp](https://img.shields.io/badge/WhatsApp-Evolution%20%C2%B7%20YT%20WPAPI%20%C2%B7%20Cloud%20API-25d366)','![AI](https://img.shields.io/badge/AI-donation%20assistant-7d64ff)'],
 gifalt='Flux Call Center tour: the chat list and three chats with assistant replies, the AI assistant summary, scheduled WhatsApp reports and the line settings',
 why='''A charity's busiest channel is WhatsApp on one phone: "How do I donate?", "Here is my receipt", "Can the certificate say my mother's name?". On a phone the team can't share it, nothing is linked to the donation, and evenings and weekends go unanswered. Flux Call Center moves the line into WordPress:

- 💬 **Shared inbox.** Everyone with access sees the same chats live, assigns them, labels them, leaves internal notes the donor never sees.
- 🌱 **Donations from the chat.** Open a donation, send a payment link or the IBAN, match the receipt, create and send the certificate, without leaving the conversation.
- 🤖 **An assistant that knows the rules.** It answers questions, takes the order, writes the names for the certificates, reads the receipt, and says "an authorised person will approve it" instead of promising. Anything it can't do goes to the team.
- 📈 **Reports to WhatsApp.** Daily or weekly donation summaries to staff and managers, with a motivating line at the end.''',
 features='''### Inbox
- Live chat list with unread, assigned to me and unassigned filters; search or start a new chat; several lines (one per branch).
- Quick replies (type `/`), labels, assignment, internal notes, reminders, auto replies and office hours.
- Photos, voice messages, documents, contact cards, replies and reactions; old messages are paged in from Evolution.
- Contact panel: donations, certificates, consents, memory and a short AI summary of the chat. Block list for people who don't want messages.
- Works on mobile and in the Flux App as a native WhatsApp screen.

### Donations and certificates
- Open a donation from the chat (one donation, one payment, a certificate per name), send a payment link or bank details, cancel an unpaid one with confirmation.
- Receipts sent on WhatsApp are read and matched to the donation, with a warning if a receipt was used before; approval stays with an authorised person.
- Create a certificate in the chat and send it; the person it is for and the referring staff member get theirs too.

### AI assistant (through Flux AI)
- **AI assistant** page: activity summary (spend, questions, donations, receipts, hand-offs), the chats it handled, instructions with versions, documents, *Edit with AI*, a test chat with files and a tool-call log, a benchmark with scenarios and a judge, memory and background jobs.
- Reply suggestions for agents, voice-to-text, chat summaries, images in answers when a renderer is available.
- Staff tools: staff recognised by their Flux Roles number can open donations for donors, resend certificates and ask for reports.

### Reports
- **WhatsApp reports:** scheduled donation reports for staff (their own) and managers (totals per staff), daily, weekly or monthly, with a preview and "Send me a test".''',
 shots=[('chats','Chat with assistant replies'),('chat2','Volunteer question'),('assistant','AI assistant: activity and chats'),('reports','Scheduled WhatsApp reports'),('settings','Lines and webhook'),('dark','Dark mode with Flux Admin'),(['mobile','mobile-chat'],'Mobile: chat list and chat')],
 req='A WhatsApp line through Flux Contact (Evolution API 2.3+, Flux WPAPI or Cloud API). Optional: Flux Donation and Flux Certificate (donations and certificates from the chat), Flux AI (assistant and suggestions), Flux Roles (staff), Flux App.',
 first='''1. In **Communication › Connections** (Flux Contact) add the WhatsApp number and connect it.
2. **Call Center › Settings › Connections**: pick that line, **Test connection**, then **Set up webhook**; optionally **Import recent chats**.
3. Add quick replies and labels under **Settings**, and turn on the assistant under **Settings › AI and assistant** once Flux AI has a key.
4. Open **Call Center › Chats** and answer the first message.''',
 note='> ⚠️ On Hostinger the live refresh runs every 10 seconds to stay inside the rate limit. Keep the webhook secret private: anyone with the address could post fake messages, so regenerate it if it leaks.',
 roadmap=[(1,'Live shared inbox with quick replies, labels and assignment'),(1,'Donations, receipts and certificates from the chat'),(1,'AI donation assistant with benchmark and memory'),(1,'Scheduled WhatsApp reports for staff and managers'),(1,'Cloud API and Flux WPAPI lines, native screen in Flux App'),(0,'Broadcast lists with opt-in'),(0,'Satisfaction question after a chat closes')]),
'tr':dict(name='Flux Chat',
 tagline='WordPress içinde WhatsApp gelen kutusu; bağış alan bir yapay zekâ asistanıyla.',
 desc="Flux Chat WhatsApp hattınızın tüm sohbetlerini panelde canlı gösterir: gelen ve giden mesajlar, telefondan yazılanlar ve sertifika mesajları. Ekip hazır cevaplar, etiketler, atama ve dahili notlarla cevap verir; bağış (Flux Donate) ve sertifika (Flux Certificates) sohbetten kaydedilir. Yapay zekâ asistanı bağışçıyla konuşur, dekontu okur, gerekince bir kişiye devreder.",
 badges=['![WhatsApp](https://img.shields.io/badge/WhatsApp-Evolution%20%C2%B7%20YT%20WPAPI%20%C2%B7%20Cloud%20API-25d366)','![AI](https://img.shields.io/badge/yapay%20zekâ-bağış%20asistanı-7d64ff)'],
 gifalt='Flux Chat turu: sohbet listesi ve asistan cevaplı üç sohbet, AI asistan özeti, zamanlanmış WhatsApp raporları ve hat ayarları',
 why='''Bir derneğin en yoğun kanalı tek bir telefondaki WhatsApp'tır: "Nasıl bağış yaparım?", "Dekontum ekte", "Sertifikaya annemin adı yazılabilir mi?". Telefonda ekip bunu paylaşamaz, hiçbir şey bağışa bağlanmaz, akşamlar ve hafta sonları cevapsız kalır. Flux Chat hattı WordPress'e taşır:

- 💬 **Ortak gelen kutusu.** Yetkisi olan herkes aynı sohbetleri canlı görür; atar, etiketler, bağışçının görmediği dahili not bırakır.
- 🌱 **Sohbetten bağış.** Konuşmadan çıkmadan bağış açın, ödeme linki ya da IBAN gönderin, dekontu eşleştirin, sertifikayı oluşturup gönderin.
- 🤖 **Kuralları bilen asistan.** Soruları cevaplar, siparişi alır, sertifika isimlerini yazar, dekontu okur; söz vermek yerine "yetkili onaylayacak" der. Yapamadığını ekibe devreder.
- 📈 **WhatsApp'a raporlar.** Personele ve yöneticilere günlük ya da haftalık bağış özetleri, sonunda bir motivasyon cümlesiyle.''',
 features='''### Gelen kutusu
- Okunmamış, bana atanan ve atanmamış süzgeçli canlı sohbet listesi; ara ya da yeni sohbet başlat; birden çok hat (şube başına bir hat).
- Hazır cevaplar (`/` yazın), etiketler, atama, dahili notlar, hatırlatıcılar, otomatik yanıtlar ve mesai saatleri.
- Fotoğraf, sesli mesaj, belge, kişi kartı, alıntı ve tepkiler; eski mesajlar Evolution'dan sayfa sayfa gelir.
- Kişi paneli: bağışlar, sertifikalar, izinler, hafıza ve sohbetin kısa AI özeti. Mesaj istemeyenler için kara liste.
- Mobilde ve Flux App'da yerel WhatsApp ekranı olarak çalışır.

### Bağış ve sertifika
- Sohbetten bağış açma (tek bağış, tek ödeme, her isme ayrı sertifika), ödeme linki ya da banka bilgisi gönderme, ödenmemiş bağışı onayla iptal.
- WhatsApp'tan gelen dekont okunur ve bağışla eşleştirilir; daha önce kullanılmış dekontta uyarı verir; onay yetkili kişide kalır.
- Sohbette sertifika oluşturup gönderme; adına bağış yapılan kişiye ve referans personele de gider.

### Yapay zekâ asistanı (Flux AI üzerinden)
- **AI asistan** sayfası: çalışma özeti (harcama, sorular, bağışlar, dekontlar, devirler), yazdığı sohbetler, sürümlü talimat, belgeler, *AI ile düzenle*, dosya ve araç logu olan test sohbeti, senaryolu ve hakemli benchmark, hafıza ve arka plan işleri.
- Temsilciye cevap önerisi, sesli mesajı yazıya çevirme, sohbet özetleri, çevirici varsa cevapta görsel.
- Personel araçları: Flux Roles numarasıyla tanınan personel bağışçı adına bağış açar, sertifikayı yeniden gönderir, rapor ister.

### Raporlar
- **WhatsApp raporları:** personele (kendi bağışları) ve yöneticilere (personel başına toplamlar) günlük, haftalık ya da aylık zamanlanmış bağış raporları; önizleme ve "Bana test gönder".''',
 shots=[('chats','Asistan cevaplı sohbet'),('chat2','Gönüllülük sorusu'),('assistant','AI asistan: özet ve sohbetler'),('reports','Zamanlanmış WhatsApp raporları'),('settings','Hatlar ve webhook'),('dark','Flux Admin ile koyu tema'),(['mobile','mobile-chat'],'Mobil: sohbet listesi ve sohbet')],
 req="Flux Mail üzerinden bir WhatsApp hattı (Evolution API 2.3+, Flux WPAPI ya da Cloud API). İsteğe bağlı: Flux Donate ve Flux Certificates (sohbetten bağış ve sertifika), Flux AI (asistan ve öneriler), Flux Roles (personel), Flux App.",
 first='''1. **İletişim › Bağlantılar**'da (Flux Mail) WhatsApp numarasını ekleyip bağlayın.
2. **Çağrı Merkezi › Ayarlar › Bağlantılar**: o hattı seçin, **Bağlantıyı test et**, ardından **Webhook'u kur**; isterseniz **Son sohbetleri içe aktar**.
3. **Ayarlar**'da hazır cevapları ve etiketleri ekleyin; Flux AI'de anahtar varsa asistanı **Ayarlar › Yapay zekâ ve asistan**'dan açın.
4. **Çağrı Merkezi › Sohbetler**'i açıp ilk mesaja cevap verin.''',
 note="> ⚠️ Hostinger'da hız sınırına takılmamak için canlı yenileme 10 saniyede bir çalışır. Webhook'taki gizli anahtarı paylaşmayın: adresi bilen sahte mesaj gönderebilir, sızarsa anahtarı yenileyin.",
 roadmap=[(1,'Hazır cevaplı, etiketli ve atamalı canlı ortak gelen kutusu'),(1,'Sohbetten bağış, dekont ve sertifika'),(1,'Benchmark ve hafızalı AI bağış asistanı'),(1,'Personel ve yöneticilere zamanlanmış WhatsApp raporları'),(1,"Cloud API ve Flux WPAPI hatları, Flux App'da yerel ekran"),(0,'İzinli toplu duyuru listeleri'),(0,'Sohbet kapanınca memnuniyet sorusu')]),
}
