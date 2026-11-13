# Skill kontrol listesi

## Ön bölüm
- [ ] name kebab-case, ≤64, klasör adıyla aynı, "claude"/"anthropic" yok
- [ ] description ne yaptığını VE ne zaman kullanılacağını söylüyor
- [ ] description'da kullanıcının gerçekten yazacağı 2-4 tetikleyici ifade var
- [ ] description < > içermiyor, 1024 karakterin altında
- [ ] allowed-tools yalnız gerçekten kullanılan araçlar

## Gövde
- [ ] İlk satırlarda amaç ve en kritik kural
- [ ] Adımlar numaralı, emir kipinde, belirsiz kelime yok ("uygun şekilde", "gerekirse" yerine somut ölçüt)
- [ ] Hangi aracın hangi parametreyle çağrılacağı yazılı
- [ ] Araç yoksa / veri boşsa ne yapılacağı yazılı; uydurma yasağı var
- [ ] Çıktının biçimi (uzunluk, başlık, madde) tanımlı; kısa örnek var
- [ ] Uzun ayrıntılar references/ dosyalarında, gövdede yalnız bağlantısı
- [ ] Yazma/değiştirme işlemleri kullanıcı onayına bırakılıyor

## Tetiklenme
- [ ] Başka bir skill'le çakışmıyor (flxai_skill_list ile karşılaştır)
- [ ] Aşırı tetiklenme riski: genel kelimeler ("rapor", "yardım") tek başına tetiklemiyor
- [ ] Az tetiklenme riski: eşanlamlılar ve kısaltmalar eklendi

## Test
- [ ] evals/evals.json: ≥5 tetiklenmeli, ≥3 tetiklenmemeli istem
- [ ] 1-3 fonksiyonel test, ölçülebilir beklentilerle
