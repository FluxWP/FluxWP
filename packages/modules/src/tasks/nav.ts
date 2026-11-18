// Tarayıcı geçmişi: kullanıcı geçişleri (doküman, sayfa, liste) geçmişe yazılır; yönlendirmeler (örn. dokümanın
// ilk sayfasına geçiş) geçmişi kirletmesin diye yerini değiştirir.
let replaceNext = false;
export const navReplace = () => void (replaceNext = true);
export const takeReplace = (): boolean => {
  const r = replaceNext;
  replaceNext = false;
  return r;
};
