// Authored fixture labels only. Never applied to a connected WordPress site's data.
let locale: 'tr' | 'en' = 'tr';
export const setDemoLocale = (value: string) => { locale = value.toLowerCase().startsWith('en') ? 'en' : 'tr'; };
export const getDemoLocale = () => locale;

const english: Record<string, string> = {
  'Demo Kullanıcı': 'Demo User', 'Demo Yönetici': 'Demo Admin', 'Demo Site': 'Demo Site', 'Örnek Site': 'Example Site', 'Yönetici': 'Administrator', 'tr_TR': 'en_US',
  'Başlangıç': 'Dashboard', 'Çağrı Merkezi': 'Inbox', 'Sohbetler': 'Chats', 'Ayarlar': 'Settings', 'Görevler': 'Tasks', 'Kullanım panosu': 'Usage', 'Posta kutusu': 'Mail',
  'Bağışlar': 'Donations', 'Panel': 'Dashboard', 'Tüm bağışlar': 'All donations', 'Bağışçılar': 'Donors', 'Sertifikalar': 'Certificates', 'Formlar': 'Forms',
  'Sayfalar': 'Pages', 'Yazılar': 'Posts', 'Tüm yazılar': 'All posts', 'Yeni ekle': 'Add new', 'Etkinlikler': 'Events', 'Ortam': 'Media', 'Site Analitik': 'Site Analytics',
  'Sosyal Medya': 'Social Media', 'TV Kumandası': 'TV Remote', 'Eklentiler': 'Plugins', 'Kullanıcılar': 'Users', 'Onay bekleyenler': 'Pending approval',
  'Yayımlanan yazılar': 'Published posts', 'Bu ay 4 yeni yazı': '4 new posts this month', 'Taslaklar': 'Drafts', 'Okunmamış mesajlar': 'Unread messages',
  'Açık görev': 'Open tasks', '1 gecikmiş': '1 overdue', 'Canlı ziyaretçi': 'Live visitors', 'Onay bekleyen yorum': 'Pending comments', 'Son içerikler': 'Recent content',
  'Yeni ürün koleksiyonu': 'New product collection', 'Ekibimizle tanışın': 'Meet our team', 'Kasım içerik planı': 'November content plan',
  'Yazı · Yayımlandı': 'Post · Published', 'Sayfa · Güncellendi': 'Page · Updated', 'Yazı · Taslak': 'Post · Draft',
  '10 dk önce': '10 min ago', '42 dk önce': '42 min ago', '2 sa önce': '2 hr ago', 'Görevlerim': 'My tasks',
  'Haftalık blog yazısını hazırla': 'Prepare the weekly blog post', 'Ürün görsellerini güncelle': 'Update product images', 'İçerik': 'Content', 'Medya': 'Media', 'Bugün': 'Today', 'Yarın': 'Tomorrow',
  'Yeni ürün koleksiyonu yayında': 'Our new product collection is live', 'Web siteniz için beş içerik fikri': 'Five content ideas for your website',
  'Ürün görsellerimiz yenilendi': 'Our product images have been updated', 'Yıl sonu değerlendirmesi': 'Year-end review', 'Basında biz: Ekim 2026': 'In the news: October 2026', 'Müşterilerimize teşekkürler': 'Thank you to our customers',
  'Yeni koleksiyonumuzu keşfedin. Ürünleri ve ayrıntıları bu yazıda bulabilirsiniz.': 'Explore our new collection. Find the products and details in this post.',
  'Hakkımızda': 'About us', 'Hizmetler': 'Services', 'İletişim': 'Contact', 'KVKK aydınlatma metni': 'Privacy notice',
  'Ad': 'Name', 'Ad soyad': 'Full name', 'Ad, e-posta ya da telefon': 'Name, email or phone', 'Aktif': 'Active', 'Ara': 'Call', 'Arama': 'Call', 'Arşiv': 'Archived',
  'Bağış': 'Donation', 'Bağış, sipariş ve formlardan kişiler kendiliğinden gelir.': 'Contacts are added from your site activity and forms.',
  'Değer': 'Value', 'Durum': 'Status', 'Düzenle': 'Edit', 'E-posta': 'Email', 'E-posta izni': 'Email consent', 'E-posta ya da telefon gerekli.': 'Email or phone is required.',
  'Eklendi': 'Added', 'Eklenme': 'Date added', 'Etiket': 'Tag', 'Etiket ekle': 'Add tag', 'Etiketi kaldır': 'Remove tag', 'Etiketler': 'Tags', 'Etkileşim ekle': 'Add activity',
  'Genel bakış': 'Overview', 'Görüşme': 'Meeting', 'Kaydedildi': 'Saved', 'Kaynak': 'Source', 'Kayıp riski': 'At risk', 'Kişi': 'Contact', 'Kişiler': 'Contacts',
  'Kişi silinsin mi?': 'Delete this contact?', 'Kişi yok': 'No contacts', 'Kurum': 'Company', 'Metin': 'Text', 'Not': 'Note', 'Not ekle': 'Add note', 'Panelde aç': 'Open in dashboard',
  'Reklam': 'Advertising', 'Reklam izni': 'Advertising consent', 'Risk': 'Risk', 'Rol': 'Role', 'SMS izni': 'SMS consent', 'Segment kuralları': 'Segment rules', 'Segment yok': 'No segments', 'Segmentler': 'Segments',
  'Segmentleri panelde kural ekleyerek oluşturun.': 'Create segments by adding rules in your dashboard.', 'Sil': 'Delete', 'Silindi': 'Deleted', 'Sipariş': 'Order', 'Son etkileşim': 'Last activity',
  'Son ödeme': 'Last payment', 'Telefon': 'Phone', 'Toplam değer': 'Total value', 'Tür': 'Type', 'WhatsApp izni': 'WhatsApp consent', 'Yeni kişi': 'New contact',
  'Zaman tüneli': 'Timeline', 'Zaman tüneli ve etiketleri de silinir.': 'The timeline and tags will also be deleted.', 'Ödeme yapan': 'Paying contacts', 'İzin': 'Consent', 'İzinler': 'Consent', 'Şehir': 'City',
  'Düzenli müşteriler': 'Returning customers', 'Yeni kişiler': 'New contacts', 'Müşteri': 'Customer', 'Editör': 'Editor', 'Bülten': 'Newsletter', 'Etkinlik': 'Event',
  'Son altı ayda üç veya daha fazla işlem': 'Three or more transactions in the last six months', '90 gündür etkileşim yok': 'No activity in 90 days',
  'Yeni içeriklerle ilgili bilgi istiyor.': 'Interested in updates about new content.', 'Açık': 'On', 'Kapalı': 'Off',
  '+{{data.new30|number}} son 30 gün': '+{{data.new30|number}} in the last 30 days', '{{data.repeat|number}} tekrar': '{{data.repeat|number}} returning',
  'Site ziyaretçisi': 'Site visitor', 'Site sohbeti': 'Site chat', 'Merhaba, kurumsal bağış için kiminle görüşebilirim?': 'Hi, who can I talk to about a corporate donation?',
  'Yanlış dosyayı gönderdim, kusura bakmayın.': 'Sorry, I sent the wrong file.', '57 dk önce': '57 min ago', 'şimdi': 'just now',
  'Günlük brifing': 'Daily briefing', 'Zamanlanmış görev: Günlük brifing': 'Scheduled task: Daily briefing',
  'Dün 14 bağış geldi (6.300 ₺), önceki güne göre %18 fazla. 3 dekont onay bekliyor. Sitede hata yok.': 'Yesterday brought 14 donations (₺6,300), 18% more than the day before. 3 receipts are waiting for approval. No site errors.',
  'Bağışçılara teşekkür e-postası': 'Thank-you email to donors', 'Bu ay bağış yapanlara kısa bir teşekkür e-postası hazırla ve gönder.': 'Draft and send a short thank-you email to this month\'s donors.',
  'Taslak hazır. 42 kişiye göndermek için onayınız gerekiyor.': 'The draft is ready. Sending it to 42 people needs your approval.', 'E-posta gönder: 42 bağışçıya teşekkür': 'Send email: thank 42 donors',
  'Ekim raporu': 'October report', 'Ekim ayı özetini çıkar.': 'Summarize October.', 'Ekimde 212 bağış, 1.940 fidan.': '212 donations and 1,940 saplings in October.', 'Tamam.': 'Done.',
  'Destek': 'Support', 'Diğer': 'Other', 'Yorumlar': 'Comments', 'Kişi kaydı': 'Contact record',
};

export function localizeDemo<T>(value: T, language = locale): T {
  if (language !== 'en') return value;
  if (typeof value === 'string') {
    if (Object.hasOwn(english, value)) return english[value] as T;
    const paragraph = value.match(/^<p>([^<]*)<\/p>$/);
    return (paragraph && Object.hasOwn(english, paragraph[1]!) ? `<p>${english[paragraph[1]!]}</p>` : value) as T;
  }
  if (Array.isArray(value)) return value.map(v => localizeDemo(v, language)) as T;
  if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).map(([key, v]) => [key, localizeDemo(v, language)])) as T;
  return value;
}

export async function localizedDemoResponse(response: Response, language = locale): Promise<Response> {
  if (language !== 'en' || response.status === 204 || response.status === 304 || !response.headers.get('content-type')?.includes('application/json')) return response;
  const headers = new Headers(response.headers); headers.delete('content-length');
  return new Response(JSON.stringify(localizeDemo(await response.json(), language)), { status: response.status, headers });
}
