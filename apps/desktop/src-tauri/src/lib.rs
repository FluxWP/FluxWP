mod embed;
mod oauth;
mod secure;

use tauri::Manager;

/// Gömülü panel sayfasını yeniden yükler (çocuk webview).
#[tauri::command]
fn webview_reload(app: tauri::AppHandle, label: String) -> Result<(), String> {
    let wv = app.get_webview(&label).ok_or("webview yok")?;
    wv.eval("location.reload()").map_err(|e| e.to_string())
}

pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_http::init())
        .plugin(tauri_plugin_opener::init())
        .plugin(tauri_plugin_updater::Builder::new().build())
        .plugin(tauri_plugin_process::init())
        .plugin(tauri_plugin_notification::init())
        .manage(oauth::OAuthState::default())
        .invoke_handler(tauri::generate_handler![
            secure::secure_get,
            secure::secure_set,
            secure::secure_remove,
            oauth::oauth_listen,
            oauth::oauth_cancel,
            webview_reload,
            embed::embed_open,
            embed::embed_message,
        ])
        .run(tauri::generate_context!())
        .expect("tauri çalıştırılamadı");
}
