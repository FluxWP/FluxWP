# Agent Skills standardı (özet)

Skill = klasör. Claude.ai, Claude Code ve Flux AI aynı biçimi okur.

```
bagisci-incelemesi/
├── SKILL.md          # zorunlu, tam bu ad (büyük harf)
├── references/       # gerektiğinde okunan belgeler
├── assets/           # şablonlar, logolar, yazı tipleri
├── scripts/          # çalıştırılabilir kod (Flux AI'da şimdilik saklanır, çalışmaz)
└── evals/            # Flux AI testleri (standart dışı, isteğe bağlı)
```

Klasörde README.md olmaz; belge SKILL.md ya da references/ içindedir.

## SKILL.md ön bölümü (YAML)

| Alan | Zorunlu | Kural |
|---|---|---|
| name | evet | kebab-case, ≤64, klasör adıyla aynı, boşluk/büyük harf/alt çizgi yok, "claude"/"anthropic" yok |
| description | evet | ≤1024 karakter, ne yaptığı + ne zaman kullanılacağı, < > yok |
| license | hayır | ör. MIT, GPL-2.0-or-later |
| compatibility | hayır | ≤500, ortam gereksinimi (ör. "Flux CRM gerekir") |
| allowed-tools | hayır | boşlukla ayrılmış araç adları |
| metadata | hayır | serbest anahtarlar: title, version, author … |

## Aşamalı açıklama (3 seviye)
1. Ad + açıklama her sohbette sistem metninde (kısa tutun).
2. Gövde, skill işe uyduğunda okunur.
3. references/, assets/ dosyaları yalnız gerektiğinde okunur (flxai_skill_file).

## İyi açıklama örneği
"Bağışçıların son 90 gündeki bağışlarını inceler, tekrar bağış yapma olasılığına göre sıralar ve teşekkür mesajı taslağı yazar. Kullanıcı 'bağışçı incelemesi', 'sadık bağışçılar', 'kime teşekkür edelim' dediğinde kullan."

## Kötü açıklama örnekleri
- "Bağışlarla ilgili yardımcı olur." (çok genel, ne zaman kullanılacağı yok)
- "Gelişmiş çok sayfalı belge sistemi." (tetikleyici yok)
