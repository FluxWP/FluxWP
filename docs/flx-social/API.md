# Flux Social — uygulama

Gönderi, hesap ve onay uçları `flx-social/v1` altındadır (yetki `flxsm_post` / `flxsm_publish` / `flxsm_manage`). Liste yanıtına `pages`, `when_label`, `accounts_label` ve `excerpt` eklendi.

`GET /wp-json/flx-social/v1/calendar` — bu ay (veya `from`/`to` unix) içindeki zamanlanan ve yayımlanan gönderiler. `page`, `per_page`.

Medya yazarı uygulama bileşeni `flxsm-composer` (sonra). Şimdilik panel yedeği `admin.php?page=flx-social`.
