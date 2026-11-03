# FluxWP Panel: kurulum

1. Flux Updater'den **FluxWP Panel**'i kurup etkinleştirin. Ekran: Görünüm › Tema Ayarları › Ön ayarlar (tema desteklemiyorsa Görünüm › Ön ayarlar).
2. Bir ön ayar eklentisi kurun (ör. **Örnek Preset**). Boş sitede kendiliğinden uygulanır. İçerikli sitede Ön ayarlar ekranında bildirim çıkar.
3. **Önizle**: her satırda şu anki değer, ön ayarın değeri ve durum görünür. Uygula'ya basınca yalnız "Eklenecek" ve "Değişecek" satırlar yazılır.
   - **Yalnız boşları doldur**: dolu değerlere hiç dokunmaz. Var olan (canlı) sitede bunu kullanın.
   - "Elle değiştirilmiş" satırlar atlanır. İsterseniz "Yine de uygula" ile tek tek zorlayın.
4. **Kurum profili** sekmesi: ad, imza, WhatsApp, IBAN, adres, sosyal medya. Eklentiler buradan okur.
5. **Geçmiş**: her uygulama ve yama "Geri al" ile geri alınır.
6. **Dışa / içe aktar**: "Bu siteden şablon çıkar" sitenin şu anki halini JSON olarak indirir (şifre, API key, token yazılmaz). Başka sitede "Şablon içe aktar" ile yükleyip önizleyerek uygularsınız.
7. **AI ve MCP**: Flux AI sohbetinde "footer'a Instagram linki ekle" gibi istekler yama olarak önizlenir, onayınızla uygulanır. Dış MCP istemcilerinin yamaları "Onay bekleyen yamalar"a düşer. İsterseniz Dışa / içe aktar sekmesinden doğrudan uygulamayı açabilirsiniz.

## Canlı sitede ilk geçiş (Örnek Vakfı)
1. Flux Backup ile yedek alın.
2. FluxWP Panel + Örnek Preset'yi kurun, etkinleştirin.
3. Görünüm › Tema Ayarları › Ön ayarlar › Örnek Vakfı › **Yalnız boşları doldur** → önizlemeyi kontrol edin → Uygula. Görünür değişiklik olmaz; değerler ayarlara açıkça yazılır ve kurum profili dolar.
4. Dışa / içe aktar › **Bu siteden şablon çıkar** (Sayfalar dahil) → JSON'u bana gönderin; canlıda panelden girilmiş değerler Örnek Vakfı ön ayarına işlenir.
5. LiteSpeed › Purge All.

