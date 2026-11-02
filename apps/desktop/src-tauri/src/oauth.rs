//! RFC 8252 loopback yönlendirme: 127.0.0.1 üzerinde rastgele portta tek istek dinler,
//! gelen /callback adresini "oauth://callback" olayıyla arayüze verir.

use std::io::{BufRead, BufReader, Write};
use std::net::TcpListener;
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::{Arc, Mutex};
use std::time::{Duration, Instant};
use tauri::{AppHandle, Emitter, State};

#[derive(Default)]
pub struct OAuthState {
    cancel: Mutex<Option<Arc<AtomicBool>>>,
}

fn esc(s: &str) -> String {
    s.replace('&', "&amp;").replace('<', "&lt;").replace('>', "&gt;")
}

fn page(title: &str, body: &str, brand: &str) -> String {
    format!(
        "<!doctype html><meta charset=utf-8><title>{t}</title>\
<body style=\"font-family:-apple-system,Segoe UI,sans-serif;display:grid;place-items:center;height:100vh;margin:0;background:#f6f7f9;color:#14171c\">\
<div style=\"text-align:center\"><h1 style=\"font-size:22px\">{t}</h1><p style=\"color:#5f6773\">{b}</p><small style=\"color:#98a1ae\">{n}</small></div>",
        t = esc(title),
        b = esc(body),
        n = esc(brand)
    )
}

#[tauri::command]
pub fn oauth_listen(app: AppHandle, state: State<'_, OAuthState>, title: String, body: String, brand: String) -> Result<u16, String> {
    let listener = TcpListener::bind("127.0.0.1:0").map_err(|e| e.to_string())?;
    listener.set_nonblocking(true).map_err(|e| e.to_string())?;
    let port = listener.local_addr().map_err(|e| e.to_string())?.port();

    let flag = Arc::new(AtomicBool::new(false));
    if let Some(old) = state.cancel.lock().unwrap().replace(flag.clone()) {
        old.store(true, Ordering::SeqCst);
    }
    let html = page(&title, &body, &brand);

    std::thread::spawn(move || {
        let deadline = Instant::now() + Duration::from_secs(600);
        while !flag.load(Ordering::SeqCst) && Instant::now() < deadline {
            match listener.accept() {
                Ok((mut stream, _)) => {
                    let _ = stream.set_nonblocking(false);
                    let mut line = String::new();
                    if BufReader::new(&stream).read_line(&mut line).is_err() {
                        continue;
                    }
                    // "GET /callback?code=..&state=.. HTTP/1.1"
                    let path = line.split_whitespace().nth(1).unwrap_or("/").to_string();
                    if !path.starts_with("/callback") {
                        let _ = stream.write_all(b"HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
                        continue;
                    }
                    let resp = format!(
                        "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}",
                        html.len(),
                        html
                    );
                    let _ = stream.write_all(resp.as_bytes());
                    let _ = app.emit("oauth://callback", format!("http://127.0.0.1:{port}{path}"));
                    break;
                }
                Err(ref e) if e.kind() == std::io::ErrorKind::WouldBlock => std::thread::sleep(Duration::from_millis(100)),
                Err(_) => break,
            }
        }
    });
    Ok(port)
}

#[tauri::command]
pub fn oauth_cancel(state: State<'_, OAuthState>) {
    if let Some(f) = state.cancel.lock().unwrap().take() {
        f.store(true, Ordering::SeqCst);
    }
}
