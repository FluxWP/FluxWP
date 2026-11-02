//! Belirteç deposu: macOS Keychain, Windows Credential Manager, Linux keyutils.
//! Servis adı uygulama kimliği (bundle id), böylece white-label build'ler birbirinin anahtarını görmez.

use tauri::AppHandle;

fn entry(app: &AppHandle, key: &str) -> Result<keyring::Entry, String> {
    keyring::Entry::new(&app.config().identifier, key).map_err(|e| e.to_string())
}

#[tauri::command]
pub fn secure_get(app: AppHandle, key: String) -> Result<Option<String>, String> {
    match entry(&app, &key)?.get_password() {
        Ok(v) => Ok(Some(v)),
        Err(keyring::Error::NoEntry) => Ok(None),
        Err(e) => Err(e.to_string()),
    }
}

#[tauri::command]
pub fn secure_set(app: AppHandle, key: String, value: String) -> Result<(), String> {
    entry(&app, &key)?.set_password(&value).map_err(|e| e.to_string())
}

#[tauri::command]
pub fn secure_remove(app: AppHandle, key: String) -> Result<(), String> {
    match entry(&app, &key)?.delete_credential() {
        Ok(()) | Err(keyring::Error::NoEntry) => Ok(()),
        Err(e) => Err(e.to_string()),
    }
}
