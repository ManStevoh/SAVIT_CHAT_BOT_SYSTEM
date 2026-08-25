"""Install the generated RelayIQ logo sample into web + mobile branding folders."""
from pathlib import Path
from PIL import Image

ROOT = Path(r"C:\SAVIT_CHAT_BOT")
ASSETS = Path(r"C:\Users\Admin\.cursor\projects\c-SAVIT-CHAT-BOT\assets")
SRC_MARK = ASSETS / "relaysiq-mark-sample.png"
SRC_WORD = ASSETS / "relaysiq-logo-sample.png"

WEB = ROOT / "LARAVEL_BACKEND" / "public" / "images" / "branding"
MOB = ROOT / "MOBILE_APP" / "assets" / "branding"
ANDROID = ROOT / "MOBILE_APP" / "android" / "app" / "src" / "main" / "res"
WEB.mkdir(parents=True, exist_ok=True)
MOB.mkdir(parents=True, exist_ok=True)


def trim(im: Image.Image, threshold: int = 18) -> Image.Image:
    rgba = im.convert("RGBA")
    px = rgba.load()
    w, h = rgba.size
    corners = [px[1, 1], px[w - 2, 1], px[1, h - 2], px[w - 2, h - 2]]
    bg = tuple(sum(c[i] for c in corners) // 4 for i in range(4))

    def is_bg(p):
        return all(abs(p[i] - bg[i]) <= threshold for i in range(3))

    min_x, min_y, max_x, max_y = w, h, 0, 0
    found = False
    for y in range(h):
        for x in range(w):
            if not is_bg(px[x, y]):
                found = True
                min_x = min(min_x, x)
                min_y = min(min_y, y)
                max_x = max(max_x, x)
                max_y = max(max_y, y)
    if not found:
        return rgba
    pad = 10
    return rgba.crop(
        (
            max(0, min_x - pad),
            max(0, min_y - pad),
            min(w, max_x + 1 + pad),
            min(h, max_y + 1 + pad),
        )
    )


def square(im: Image.Image, size: int, fill=(0, 0, 0, 0)) -> Image.Image:
    im = im.convert("RGBA")
    im.thumbnail((size, size), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (size, size), fill)
    canvas.paste(im, ((size - im.width) // 2, (size - im.height) // 2), im)
    return canvas


def punch_near_white(im: Image.Image, threshold: int = 245) -> Image.Image:
    """Make near-white background transparent via edge flood fill."""
    rgba = im.convert("RGBA")
    w, h = rgba.size
    px = rgba.load()
    visited = [[False] * w for _ in range(h)]
    stack = [(0, 0), (w - 1, 0), (0, h - 1), (w - 1, h - 1)]
    while stack:
        x, y = stack.pop()
        if x < 0 or y < 0 or x >= w or y >= h or visited[y][x]:
            continue
        r, g, b, a = px[x, y]
        if r < threshold or g < threshold or b < threshold:
            continue
        visited[y][x] = True
        px[x, y] = (0, 0, 0, 0)
        stack.extend([(x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)])
    return rgba


mark_raw = trim(Image.open(SRC_MARK).convert("RGBA"), 20)
mark_raw = punch_near_white(mark_raw, 242)

word_raw = trim(Image.open(SRC_WORD).convert("RGBA"), 18)
word_raw = punch_near_white(word_raw, 242)

mark = square(mark_raw, 1024, (0, 0, 0, 0))
favicon = square(mark_raw, 512, (8, 12, 24, 255))
app_icon = square(mark_raw, 1024, (8, 12, 24, 255))

# Keep a light wordmark (transparent bg) and a dark-tile wordmark for dark UIs
word_light = word_raw
word_dark_canvas = Image.new("RGBA", word_raw.size, (8, 12, 24, 255))
word_dark_canvas.paste(word_raw, (0, 0), word_raw)

for d in (WEB, MOB):
    mark.save(d / "relaysiq-mark.png")
    favicon.save(d / "relaysiq-favicon.png")
    app_icon.save(d / "relaysiq-app-icon.png")
    word_light.save(d / "relaysiq-wordmark-light.png")
    word_dark_canvas.save(d / "relaysiq-wordmark-dark.png")

mipmaps = {
    "mipmap-mdpi": 48,
    "mipmap-hdpi": 72,
    "mipmap-xhdpi": 96,
    "mipmap-xxhdpi": 144,
    "mipmap-xxxhdpi": 192,
}
for folder, size in mipmaps.items():
    out = ANDROID / folder / "ic_launcher.png"
    out.parent.mkdir(parents=True, exist_ok=True)
    square(mark_raw, size, (8, 12, 24, 255)).save(out)

print("Installed RelayIQ logo sample")
print("mark", (WEB / "relaysiq-mark.png").stat().st_size)
print("favicon", (WEB / "relaysiq-favicon.png").stat().st_size)
print("wordmark", (WEB / "relaysiq-wordmark-light.png").stat().st_size)
