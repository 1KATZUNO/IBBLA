#!/usr/bin/env python3
"""Genera las imágenes de la guía «Avisos por WhatsApp» (public/images/manual/whatsapp/*.png).

Son maquetas dibujadas en HTML (no capturas reales de un teléfono): letra grande, un solo punto
resaltado por imagen y una leyenda al pie, pensadas para que las entienda una persona mayor.
Se renderizan con Chrome o Edge sin ventana.

Uso (desde la raíz del proyecto):
    python resources/manual/whatsapp/generar_imagenes.py

El número del bot de CallMeBot NO se dibuja en las imágenes a propósito: cambia de vez en cuando
(config/whatsapp.php, whatsapp.callmebot.bot_numero) y la pantalla lo muestra en texto al lado de la imagen.
"""
import os
import shutil
import subprocess
import sys
import tempfile

RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..', '..'))
SALIDA = os.path.join(RAIZ, 'public', 'images', 'manual', 'whatsapp')
ANCHO, ALTO, ESCALA = 600, 1000, 1.5

NAVEGADORES = [
    r'C:\Program Files\Google\Chrome\Application\chrome.exe',
    r'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
    r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
    r'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
    shutil.which('google-chrome') or '',
    shutil.which('chromium') or '',
]

CSS = """
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:600px;height:1000px;overflow:hidden}
body{font-family:'Segoe UI','Helvetica Neue',Arial,sans-serif;background:#fff;color:#111;position:relative}
.pie{position:absolute;left:0;right:0;bottom:0;height:150px;background:#14532d;color:#fff;display:flex;align-items:center;
  justify-content:center;text-align:center;padding:0 34px;font-size:36px;font-weight:700;line-height:1.2}
.num{position:absolute;left:24px;top:22px;width:86px;height:86px;border-radius:50%;background:#f59e0b;color:#111;font-size:56px;
  font-weight:800;display:flex;align-items:center;justify-content:center;z-index:20;box-shadow:0 4px 10px rgba(0,0,0,.35);border:5px solid #fff}
.resalta{outline:7px solid #f59e0b;outline-offset:4px;border-radius:18px;position:relative}
.nota{position:absolute;z-index:15;background:#f59e0b;color:#111;font-size:30px;font-weight:800;line-height:1.15;padding:12px 18px;border-radius:14px;
  box-shadow:0 4px 10px rgba(0,0,0,.3)}
.flecha{position:absolute;z-index:15;font-size:64px;line-height:1;color:#f59e0b;text-shadow:0 2px 0 #fff,2px 0 0 #fff,-2px 0 0 #fff,0 -2px 0 #fff}
/* WhatsApp (maqueta) */
.wa-cab{position:absolute;left:0;right:0;top:0;height:128px;background:#008069;color:#fff;display:flex;align-items:center;padding:0 22px 0 130px;gap:18px}
.wa-av{width:76px;height:76px;border-radius:50%;background:#dfe5e7;display:flex;align-items:center;justify-content:center;font-size:44px}
.wa-nombre{font-size:36px;font-weight:700;line-height:1.1}
.wa-estado{font-size:22px;opacity:.85}
.wa-chat{position:absolute;left:0;right:0;top:128px;bottom:150px;background:#efe7de;padding:24px 22px;overflow:hidden}
.dia{display:table;margin:0 auto 22px;background:#fff;color:#54656f;font-size:22px;padding:6px 18px;border-radius:12px;box-shadow:0 1px 1px rgba(0,0,0,.15)}
.bur{max-width:82%;padding:14px 18px 10px;border-radius:18px;font-size:29px;line-height:1.3;margin-bottom:18px;box-shadow:0 1px 1px rgba(0,0,0,.18);word-wrap:break-word}
.bur.sale{background:#d9fdd3;margin-left:auto;border-top-right-radius:4px}
.bur.entra{background:#fff;border-top-left-radius:4px}
.hora{display:block;text-align:right;font-size:20px;color:#667781;margin-top:4px}
.tick{color:#53bdeb;font-weight:700;letter-spacing:-6px;margin-left:8px}
.link{background:#fde68a;border-radius:8px;padding:0 4px;color:#0b5394;word-break:break-all}
"""

ICONO_ENVIAR = ('<svg width="54" height="54" viewBox="0 0 24 24" fill="#fff"><path d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg>')
ICONO_COPIAR = ('<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" '
                'stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>')


def pagina(cuerpo):
    return f'<!doctype html><html lang="es"><head><meta charset="utf-8"><style>{CSS}</style></head><body>{cuerpo}</body></html>'


def cabecera_chat():
    return ('<div class="wa-cab"><span style="position:absolute;left:24px;font-size:50px">&#8592;</span>'
            '<div class="wa-av">&#129302;</div><div><div class="wa-nombre">CallMeBot</div><div class="wa-estado">en línea</div></div></div>')


def paso1():
    return pagina("""
<div class="num">1</div>
<div style="position:absolute;left:0;right:0;top:0;height:128px;background:#1f2937;color:#fff;display:flex;align-items:center;padding:0 24px 0 130px;font-size:38px;font-weight:600">Contacto nuevo</div>
<div style="position:absolute;left:0;right:0;top:128px;bottom:150px;background:#fff;padding:26px 34px">
  <div style="display:flex;justify-content:center;margin-bottom:18px"><div style="width:104px;height:104px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:60px">&#128100;</div></div>
  <div style="font-size:25px;color:#4b5563;margin-bottom:6px">Nombre</div>
  <div class="resalta" style="font-size:40px;font-weight:700;padding:12px 20px;border:2px solid #9ca3af;margin-bottom:30px;background:#fff">CallMeBot</div>
  <div style="font-size:25px;color:#4b5563;margin-bottom:6px">Teléfono</div>
  <div class="resalta" style="font-size:36px;padding:12px 20px;border:2px solid #9ca3af;margin-bottom:12px;color:#6b7280;background:#fff">+34 ··· ·· ·· ··</div>
  <div style="font-size:24px;color:#374151;line-height:1.25;margin-bottom:30px">El número del bot está escrito en esta pantalla.</div>
  <div class="resalta" style="background:#16a34a;color:#fff;font-size:38px;font-weight:700;text-align:center;padding:16px;border-radius:14px">Guardar</div>
</div>
<div class="pie">Guarde el número del bot como contacto</div>""")


def paso2():
    return pagina(f"""
<div class="num">2</div>{cabecera_chat()}
<div class="wa-chat"><div class="dia">HOY</div></div>
<div style="position:absolute;left:0;right:0;bottom:150px;height:128px;background:#f0f2f5;display:flex;align-items:center;padding:0 16px;gap:14px">
  <div style="flex:1;background:#fff;border-radius:40px;padding:16px 24px;font-size:28px;line-height:1.2;min-height:88px;display:flex;align-items:center">I allow callmebot to send me messages</div>
  <div class="resalta" style="width:92px;height:92px;border-radius:50%;background:#00a884;display:flex;align-items:center;justify-content:center">{ICONO_ENVIAR}</div>
</div>
<div class="nota" style="right:22px;bottom:352px;max-width:330px">Toque aquí para enviar</div>
<div class="flecha" style="right:50px;bottom:268px">&#8595;</div>
<div class="pie">Escríbale exactamente este mensaje</div>""")


def paso3():
    return pagina(f"""
<div class="num">3</div>
<div style="position:absolute;left:0;right:0;top:0;height:128px;background:#008069;display:flex;align-items:center;justify-content:flex-end;padding:0 26px;gap:30px">
  <div class="resalta" style="width:84px;height:84px;border-radius:50%;display:flex;align-items:center;justify-content:center">{ICONO_COPIAR}</div>
  <span style="font-size:48px;color:#fff">&#8942;</span></div>
<div class="wa-chat" style="padding-top:118px">
  <div class="bur entra" style="background:#cfe9ff;box-shadow:0 0 0 6px #f59e0b;max-width:92%;font-size:30px">API Activated for your phone number. Your APIKEY is 1234567<br><br>You can now send messages using the API:<br>
    <span class="link">https://api.callmebot.com/whatsapp.php?phone=+506xxxxxxxx&amp;text=This+is+a+test&amp;apikey=1234567</span><span class="hora">10:42</span></div>
</div>
<div class="nota" style="right:22px;top:142px;max-width:400px">Toque este botón para copiar</div>
<div class="pie">Deje el dedo sobre el mensaje del bot y toque Copiar</div>""")


def paso4():
    return pagina(f"""
<div class="num">4</div>{cabecera_chat()}
<div class="wa-chat" style="padding-top:34px"><div class="dia">HOY</div>
  <div class="bur entra" style="box-shadow:0 0 0 6px #f59e0b">Hola Ana, esta es una prueba de su iglesia. Si usted lee este mensaje, sus avisos por WhatsApp quedaron activados. ¡Dios le bendiga!<span class="hora">10:45</span></div>
</div>
<div class="nota" style="left:30px;top:520px;max-width:420px">Así le llegarán los avisos</div>
<div class="flecha" style="left:90px;top:430px">&#8593;</div>
<div class="pie">Así se ve el mensaje de prueba cuando todo salió bien</div>""")


IMAGENES = {
    'paso-1-contacto.png': paso1,
    'paso-2-mensaje.png': paso2,
    'paso-3-enlace.png': paso3,
    'paso-4-prueba.png': paso4,
}


def navegador():
    for ruta in NAVEGADORES:
        if ruta and os.path.exists(ruta):
            return ruta
    sys.exit('No encontré Chrome ni Edge para dibujar las imágenes.')


def main():
    os.makedirs(SALIDA, exist_ok=True)
    exe = navegador()
    with tempfile.TemporaryDirectory() as tmp:
        for nombre, construir in IMAGENES.items():
            html = os.path.join(tmp, nombre.replace('.png', '.html'))
            with open(html, 'w', encoding='utf-8') as f:
                f.write(construir())
            destino = os.path.join(SALIDA, nombre)
            subprocess.run([
                exe, '--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-sandbox',
                f'--force-device-scale-factor={ESCALA}', f'--window-size={ANCHO},{ALTO}',
                f'--screenshot={destino}', 'file:///' + html.replace(chr(92), '/'),
            ], check=True, capture_output=True, timeout=120)
            print('OK', destino, os.path.getsize(destino) // 1024, 'KB')


if __name__ == '__main__':
    main()
