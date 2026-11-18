mod embed;
mod secure;

use tauri::Manager;

/// Gömülü panel sayfasını yeniden yükler (çocuk webview).
#[tauri::command]
fn webview_reload(app: tauri::AppHandle, label: String) -> Result<(), String> {
    let wv = app.get_webview(&label).ok_or("webview yok")?;
    wv.eval("location.reload()").map_err(|e| e.to_string())
}

pub fn run() {
    let builder = tauri::Builder::default();
    // Windows/Linux start a new process for a fluxwp:// link; hand it to the running one.
    #[cfg(any(target_os = "windows", target_os = "linux"))]
    let builder = builder.plugin(tauri_plugin_single_instance::init(|app, _args, _cwd| {
        if let Some(w) = app.get_webview_window("main") {
            let _ = w.set_focus();
        }
    }));
    let builder = builder
        .plugin(tauri_plugin_deep_link::init())
        .plugin(tauri_plugin_http::init())
        .plugin(tauri_plugin_opener::init());
    #[cfg(not(feature = "app-store"))]
    let builder = builder
        .plugin(tauri_plugin_updater::Builder::new().build())
        .plugin(tauri_plugin_process::init());
    builder
        .plugin(tauri_plugin_notification::init())
        .invoke_handler(tauri::generate_handler![
            secure::secure_get,
            secure::secure_set,
            secure::secure_remove,
            webview_reload,
            embed::embed_open,
            embed::embed_message,
        ])
        .run(tauri::generate_context!())
        .expect("tauri çalıştırılamadı");
}
