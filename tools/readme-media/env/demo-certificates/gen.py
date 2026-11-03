# Demo sertifika arka planlari (Ornek Vakfi, logosuz): Flux Certificates hazir sablonlarinin bekledigi dosya adlariyla.
# python3 gen.py <cikti klasoru>  ->  <ad>.jpg (900 px onizleme), raster/<ad>.jpg (2000 px), <ad>.pdf
import sys, os, math
from PIL import Image, ImageDraw, ImageFont
OUT = sys.argv[1]; os.makedirs(OUT + '/raster', exist_ok=True)
FD = os.path.join(os.path.dirname(os.path.abspath(__file__)), '../../../../plugins/flx-certificates/assets/fonts/')
G, G2, CREAM = (31, 110, 67), (120, 180, 110), (250, 248, 240)
def font(n, s): return ImageFont.truetype(FD + n, s)
def leaf(d, cx, cy, r, ang, col):
    pts = []
    for i in range(41):
        t = i / 40 * math.pi
        x, y = math.cos(t) * r, math.sin(t) * r * 0.42
        pts.append((x, y))
    pts += [(x, -y) for x, y in reversed(pts)]
    a = math.radians(ang)
    d.polygon([(cx + x * math.cos(a) - y * math.sin(a), cy + x * math.sin(a) + y * math.cos(a)) for x, y in pts], fill=col)
def mark(d, cx, cy, s, col=G, txt=True, tcol=None):
    leaf(d, cx - s * 0.32, cy, s * 0.5, -55, col); leaf(d, cx + s * 0.32, cy, s * 0.5, -125, G2)
    if txt:
        f = font('Montserrat-Bold.ttf', int(s * 0.42)); w = d.textlength('ÖRNEK VAKFI', font=f)
        d.text((cx - w / 2, cy + s * 0.55), 'ÖRNEK VAKFI', font=f, fill=tcol or col)
def spaced(d, cx, y, t, f, col, sp):
    w = sum(d.textlength(c, font=f) for c in t) + sp * (len(t) - 1); x = cx - w / 2
    for c in t: d.text((x, y), c, font=f, fill=col); x += d.textlength(c, font=f) + sp
def framed(W, H, title):
    im = Image.new('RGB', (W, H), CREAM); d = ImageDraw.Draw(im); u = W / 1000
    for x, y in ((0, 0), (W, 0), (0, H), (W, H)):
        a = math.degrees(math.atan2(H / 2 - y, W / 2 - x))
        for k in range(3):
            b = math.radians(a + (k - 1) * 28)
            leaf(d, x + math.cos(b) * 75 * u, y + math.sin(b) * 75 * u, 70 * u, a + (k - 1) * 28, (190, 220, 185) if k == 1 else (212, 232, 206))
    m = 28 * u; d.rectangle([m, m, W - m, H - m], outline=G, width=max(2, int(6 * u)))
    d.rectangle([m + 12 * u, m + 12 * u, W - m - 12 * u, H - m - 12 * u], outline=G2, width=max(1, int(2 * u)))
    mark(d, W / 2, H * 0.085, 60 * u)
    fs = int(54 * u * (0.8 if H > W else 1)); spaced(d, W / 2, H * 0.19, title, font('Cinzel-Bold.ttf', fs), (25, 45, 70), 6 * u)
    return im
def banded(W, H, title):
    im = Image.new('RGB', (W, H), (255, 255, 255)); d = ImageDraw.Draw(im); u = W / 1000
    d.rectangle([0, 0, W * 0.155, H], fill=G); d.rectangle([W * 0.155, 0, W * 0.165, H], fill=G2)
    for i in range(14): leaf(d, W * 0.03 + (i % 3) * W * 0.05, H * 0.05 + i * H * 0.075, 18 * u, 40 + i * 20, (45, 128, 82))
    mark(d, W * 0.0775, H * 0.42, 60 * u, col=(255, 255, 255), txt=False)
    f = font('Montserrat-Bold.ttf', int(22 * u))
    for i, t in enumerate(('ÖRNEK', 'VAKFI')):
        w = d.textlength(t, font=f); d.text((W * 0.0775 - w / 2, H * 0.42 + 40 * u + i * 28 * u), t, font=f, fill=(255, 255, 255))
    d.text((W * 0.215, H * 0.1), title, font=font('Montserrat-Bold.ttf', int(62 * u)), fill=G)
    d.rectangle([W * 0.215, H * 0.235, W * 0.29, H * 0.243], fill=G2)
    return im
def save(name, im):
    im.save(f'{OUT}/raster/{name}.jpg', quality=90)
    s = im.copy(); s.thumbnail((900, 900)); s.save(f'{OUT}/{name}.jpg', quality=88)
    im.save(f'{OUT}/{name}.pdf', resolution=im.width / (11.69 if im.width > im.height else 8.27))
save('fidan-bagis-sertifikasi', framed(1400, 1981, 'FİDAN BAĞIŞ SERTİFİKASI'))
save('fidan-sertifikasi', framed(2000, 1414, 'FİDAN SERTİFİKASI'))
save('katilim-belgesi', banded(2000, 1414, 'KATILIM BELGESİ'))
print('ok', OUT)
