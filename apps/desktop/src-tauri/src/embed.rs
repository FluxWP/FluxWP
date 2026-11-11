//! Child webview for wp-admin. Navigation stays on the origin the view was opened for.
//! The panel still hears same-origin loads (classifyNavigation, parseBridgeMessage).

use tauri::{Emitter, LogicalPosition, LogicalSize, Manager, Url, WebviewUrl};
use tauri_plugin_opener::OpenerExt;

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
pub(crate) struct EmbedArgs {
    label: String,
    url: String,
    x: f64,
    y: f64,
    width: f64,
    height: f64,
    user_agent: String,
}

fn embed_allowed(url: &Url, home: &Url) -> bool {
    if url.as_str() == "about:blank" || url.as_str().starts_with("about:blank#") {
        return true;
    }
    if !url.username().is_empty() || url.password().is_some() {
        return false;
    }
    url.scheme() == home.scheme()
        && url.host() == home.host()
        && url.port_or_known_default() == home.port_or_known_default()
}

#[tauri::command]
pub async fn embed_open(app: tauri::AppHandle, args: EmbedArgs) -> Result<(), String> {
    let window = app.get_window("main").ok_or("main yok")?;
    let home: Url = args.url.parse().map_err(|e| format!("{e}"))?;
    let label = args.label.clone();
    let app_nav = app.clone();
    let builder = tauri::webview::WebviewBuilder::new(&args.label, WebviewUrl::External(home.clone()))
        .user_agent(&args.user_agent)
        .initialization_script(&format!("window.__flxEmbed = {:?};\n{BRIDGE}", args.label))
        .on_navigation(move |url| {
            if embed_allowed(url, &home) {
                let _ = app_nav.emit(
                    "embed://navigate",
                    serde_json::json!({ "label": label, "url": url.as_str() }),
                );
                return true;
            }
            if url.scheme() == "http" || url.scheme() == "https" {
                let _ = app_nav.opener().open_url(url.as_str(), None::<&str>);
            }
            false
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
