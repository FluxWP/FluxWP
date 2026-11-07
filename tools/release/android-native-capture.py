"""Capture only the application's fictional demo in an isolated CI emulator."""
import hashlib
import json
import os
from pathlib import Path
import re
import struct
import subprocess
import time
import xml.etree.ElementTree as ET

locale = os.environ['SCREENSHOT_LOCALE']
if locale not in ('tr-TR', 'en-US'):
    raise ValueError('Unsupported screenshot locale')
package = 'com.fluxwp.app'
out = Path('release-out/android-screens')
out.mkdir(parents=True, exist_ok=True)


def adb(*args, binary=False):
    return subprocess.check_output(['adb', *args], text=not binary, timeout=45)


def hierarchy():
    adb('shell', 'uiautomator', 'dump', '/sdcard/fluxwp-qa.xml')
    raw = adb('exec-out', 'cat', '/sdcard/fluxwp-qa.xml')
    (out / 'last-ui.xml').write_text(raw)
    return ET.fromstring(raw)


def bounds(node):
    values = list(map(int, re.findall(r'-?\d+', node.get('bounds', ''))))
    return values if len(values) == 4 else [0, 0, 0, 0]


def find(identifier=None, labels=(), timeout=45, scroll=False):
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        tree = hierarchy()
        for n in tree.iter('node'):
            resource = n.get('resource-id', '').split('/')[-1]
            text = n.get('text', '')
            desc = n.get('content-desc', '')
            matches = resource == identifier if identifier else any(text == x or desc == x or desc.startswith(x + ',') for x in labels)
            x1, y1, x2, y2 = bounds(n)
            if matches and 0 <= x1 < x2 <= width and 0 <= y1 < y2 <= height:
                return n
        if scroll:
            adb('shell', 'input', 'swipe', str(width // 2), str(height * 4 // 5), str(width // 2), str(height // 3), '350')
        time.sleep(0.5)
    raise AssertionError('Missing demo control: ' + str(identifier or labels))


def tap(identifier=None, labels=(), scroll=False):
    x1, y1, x2, y2 = bounds(find(identifier, labels, scroll=scroll))
    adb('shell', 'input', 'tap', str((x1 + x2) // 2), str((y1 + y2) // 2))


def shot(name):
    time.sleep(2)
    tree = hierarchy()
    ET.ElementTree(tree).write(out / f'android-{name}.xml', encoding='utf-8')
    (out / f'android-{name}.png').write_bytes(adb('exec-out', 'screencap', '-p', binary=True))


adb('install', '-r', 'release-out/android-qa/universal.apk')
adb('shell', 'wm', 'size', '1080x1920')
width, height = 1080, 1920
adb('shell', 'cmd', 'locale', 'set-app-locales', package, '--user', '0', '--locales', locale)
component = adb('shell', 'cmd', 'package', 'resolve-activity', '--brief', package).strip().splitlines()[-1]
if not component.startswith(package + '/'):
    raise AssertionError('Cannot resolve FluxWP launcher')
adb('shell', 'am', 'start', '-W', '-n', component)
try:
    node = find('welcome-skip', timeout=12)
except AssertionError:
    pass
else:
    tap('welcome-skip')
choice = find('usage-consent')
if not any(n.get('checked') == 'false' and n.get('checkable') == 'true' for n in choice.iter()):
    raise AssertionError('Optional usage sharing must be available and default off')
shot('01-connect')
tap('demo', scroll=True)
find('open-drawer')
shot('02-dashboard')
tap(labels=('Kişiler', 'Contacts'))
shot('03-contacts')
tap(labels=('Yazılar', 'Posts'))
shot('04-posts')
tap('site-switcher')
shot('06-accounts')
adb('shell', 'input', 'keyevent', '4')
tap('open-drawer')
shot('05-menu')
proof = json.loads(Path('release-out/android-qa/provenance.json').read_text())
proof.update(native=True, platform='android', locale=locale, usageSharingDefault=False, files=[])
for p in sorted(out.glob('*.png')):
    data = p.read_bytes()
    w, h = struct.unpack('>II', data[16:24])
    if data[:8] != b'\x89PNG\r\n\x1a\n' or (w, h) != (1080, 1920):
        raise AssertionError('Invalid Android screenshot')
    proof['files'].append(dict(path=p.name, width=w, height=h, sha256=hashlib.sha256(data).hexdigest()))
if len(proof['files']) != 6:
    raise AssertionError('Incomplete Android screenshot collection')
(out / 'manifest.json').write_text(json.dumps(proof, indent=2))
print('Six real Android demo screenshots captured; default-off privacy choice verified.')
