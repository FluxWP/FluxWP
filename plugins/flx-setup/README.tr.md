# Flux Setup

**Ön ayar motoru.** Bir şablon dosyasıyla (`preset.json`) siteyi tek adımda kurar: kurum profili, eklenti ayarları, renk ve fontlar, header/footer, şablonlar, sayfalar, menüler ve medya. Aynı biçimle mevcut site, Flux AI sohbetinden ya da dış MCP'den onaylı yamalarla değiştirilir.

- **Önizle, uygula, geri al.** Her satırda şu an / ön ayar / durum. Her uygulama geçmişte, tek tıkla geri alınır.
- **Elle yaptığınız değişiklik korunur.** Panelden değiştirdiğiniz değerin üzerine ön ayar güncellemesi yazmaz.
- **Migration'lar.** Ön ayar güncellemeleri tek tek önizlenip uygulanır ya da atlanır.
- **Birden çok ön ayar** (ör. fidan + kurban) aynı sitede.
- **Bu siteden şablon çıkar**, JSON içe aktar. Gizli alanlar dosyaya yazılmaz.
- **Kurum profili** (`flx_profile()`): paketler kurum adı, imza, WhatsApp, IBAN gibi bilgileri buradan okur.
- **AI ve MCP**: `preset_schema`, `preset_export`, `preset_patch_preview`, `preset_patch_apply`, `preset_revert`.

Biçim: [docs/flx-setup/FORMAT.md](../../docs/flx-setup/FORMAT.md) · Paketler için: [API.md](API.md) · Kurulum: [KURULUM.md](KURULUM.md)

[English](README.md)
