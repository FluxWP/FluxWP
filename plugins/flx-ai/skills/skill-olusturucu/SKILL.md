---
name: skill-olusturucu
description: Creates, reviews and tests skills in the Agent Skills format (SKILL.md folder). Use when the user says "skill oluştur", "yeni skill", "bu işi skill yap", "skill'i iyileştir" or asks why a skill does not trigger.
license: GPL-2.0-or-later
allowed-tools: flxai_skill_list flxai_skill_get flxai_skill_file flxai_skill_save flxai_skill_file_write flxai_skill_test
metadata:
  title: Skill creator
  version: "1"
---

# Skill oluşturucu

Kullanıcıyla birlikte Agent Skills standardında bir skill yaz, kontrol et ve test et. Kurallar `references/standart.md`, inceleme listesi `references/kontrol-listesi.md`, boş şablon `assets/SKILL-sablon.md` dosyasında; gerektiğinde flxai_skill_file ile oku.

## 1. Anla (kısa)
- Skill hangi işi yapacak, kullanıcı bunu hangi cümlelerle ister, sonuç nasıl görünmeli? Eksikse en fazla 3 soru sor; gerisini makul varsay ve söyle.
- Önce flxai_skill_list ile benzer skill var mı bak. Varsa yenisini açmak yerine onu geliştirmeyi öner.
- Hangi araçlar gerekiyor? Kullanıcının araç listesinden adlarıyla seç (allowed-tools).

## 2. Yaz
- name: kebab-case, en fazla 64 karakter, "claude"/"anthropic" içermez. Türkçe karakter yok (ör. bagisci-incelemesi).
- description: ne yaptığı + ne zaman kullanılacağı, kullanıcının gerçekten söyleyeceği tetikleyici ifadelerle. En fazla 1024 karakter, < > yok. Çok genel olmasın (aşırı tetiklenir), çok dar olmasın (hiç tetiklenmez).
- Gövde: adım adım, emir kipinde, kendi başına anlaşılır. Kritik kuralı en üste yaz. Gövde kısa kalsın (≈500 satırın altı); uzun ayrıntı, tablo, örnekler references/ dosyalarına; şablonlar assets/ altına.
- Hata durumlarını yaz: araç yoksa ne yapılır, veri boşsa ne söylenir. Uydurma yasağını ekle.
- Kaydet: flxai_skill_save (title, description, instructions, tools). Ek dosyalar: flxai_skill_file_write.

## 3. Kontrol et
references/kontrol-listesi.md'deki her maddeye bak; sorun varsa düzelt ve kullanıcıya kısa liste halinde ne değiştiğini söyle.

## 4. Test et
- evals/evals.json yaz (flxai_skill_file_write): en az 5 tetiklenmesi gereken + 3 tetiklenmemesi gereken istem (trigger_evals: query, should_trigger) ve 1-3 fonksiyonel test (evals: id, prompt, expectations[]).
- Kullanıcı onaylarsa flxai_skill_test kind=trigger, sonra her fonksiyonel test için kind=functional çalıştır. Her çalıştırmanın AI maliyeti olduğunu söyle.
- Tetiklenme %90'ın altındaysa açıklamayı düzelt (eksik tetikleyici ifade ekle ya da fazla genel kısmı daralt) ve tekrar dene. Fonksiyonel testte skill'siz cevap daha iyiyse talimatı sadeleştir.

## Çıktı
Sonunda: skill adı, tek cümle ne yaptığı, /ad ile nasıl çağrılacağı, test sonucu (varsa) ve Skill'ler ekranının bağlantısı.
