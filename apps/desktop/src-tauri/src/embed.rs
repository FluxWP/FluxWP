//! Child webview for wp-admin. Navigation and the embed bridge are emitted to the panel;
//! the runtime decides what they mean (classifyNavigation, parseBridgeMessage).

use tauri::{Emitter, LogicalPosition, LogicalSize, Manager, WebviewUrl};

const BRIDGE: &str = r#"
window.addEventListener('message', function (ev) {
  try {
    if (ev.source !== window || ev.data == null) return;
    var raw = typeof ev.data === 'string' ? ev.data : JSON.stringify(ev.data);
    if (!raw || raw.length > 10000 || raw.indexOf('type') < 0) return;
    var invoke = window.__TAURI_INTERNALS__ && window.__TAURI_INTERNALS__.invoke;
    if (invoke) invoke('embed_message', { raw: raw, label: window.__flxEmbed || '' });
  } catch (e) {}
});
"#;

#[derive(serde::Deserialize)]
#[serde(rename_all = "camelCase")]
struct EmbedArgs {
    label: String,
    url: String,
    x: f64,
    y: f64,
    width: f64,
    height: f64,
    user_agent: String,
}

#[tauri::command]
pub async fn embed_open(app: tauri::AppHandle, args: EmbedArgs) -> Result<(), String> {
    let window = app.get_window("main").ok_or("main yok")?;
    let parsed: tauri::Url = args.url.parse().map_err(|e| format!("{e}"))?;
    let label = args.label.clone();
    let app_nav = app.clone();
    let builder = tauri::webview::WebviewBuilder::new(&args.label, WebviewUrl::External(parsed))
        .user_agent(&args.user_agent)
        .initialization_script(&format!("window.__flxEmbed = {:?};\n{BRIDGE}", args.label))
        .on_navigation(move |url| {
            let _ = app_nav.emit(
                "embed://navigate",
                serde_json::json!({ "label": label, "url": url.as_str() }),
            );
            true
        });
    window
        .add_child(
            builder,
            LogicalPosition::new(args.x, args.y),
            LogicalSize::new(args.width, args.height),
        )
        .map_err(|e| e.to_string())?;
    Ok(())
}

#[tauri::command]
pub fn embed_message(app: tauri::AppHandle, raw: String, label: String) -> Result<(), String> {
    app.emit("embed://message", serde_json::json!({ "label": label, "raw": raw }))
        .map_err(|e| e.to_string())
}
