"""Extract RelayIQ icons from high-res Downloads sources and install everywhere."""
from pathlib import Path
from PIL import Image

ROOT = Path(r"C:\SAVIT_CHAT_BOT")
DOWNLOADS = Path(r"C:\Users\Admin\Downloads")
SRC_ICONS = DOWNLOADS / "ChatGPT Image Jul 23, 2026, 10_52_37 AM.png"
SRC_WORD = DOWNLOADS / "ChatGPT Image Jul 23, 2026, 10_42_33 AM.png"
SRC_BOARD = DOWNLOADS / "ChatGPT Image Jul 23, 2026, 10_42_50 AM.png"

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


def punch_black_bg(im: Image.Image, threshold: int = 28) -> Image.Image:
    """Make near-black background transparent, keep dark logo details via edge flood."""
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
        if r > threshold or g > threshold or b > threshold:
            continue
        visited[y][x] = True
        px[x, y] = (0, 0, 0, 0)
        stack.extend([(x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)])
    return rgba


# --- Favicon + Mobile App Icon (high-res pair) ---
pair = Image.open(SRC_ICONS).convert("RGBA")
w, h = pair.size
# Drop bottom label band (~22%)
cut = int(h * 0.78)
left = pair.crop((int(w * 0.04), int(h * 0.08), int(w * 0.46), cut))
right = pair.crop((int(w * 0.52), int(h * 0.05), int(w * 0.96), cut))

favicon_raw = trim(left, 22)
app_raw = trim(right, 22)

# Transparent mark for light UI / navbar
mark = punch_black_bg(square(favicon_raw, 1024, (0, 0, 0, 0)))
# Favicon keeps subtle dark tile for browser tabs
favicon = square(favicon_raw, 512, (8, 12, 24, 255))
# App icon = squircle composition already in source
app_icon = square(app_raw, 1024, (8, 12, 24, 255))

# Wordmark dark (full logo + tagline)
wordmark = trim(Image.open(SRC_WORD).convert("RGBA"), 14)

# Brand board reference
board = Image.open(SRC_BOARD).convert("RGBA")

# Feature icons strip from brand board bottom (~ last 18%)
fw, fh = board.size
strip = board.crop((int(fw * 0.05), int(fh * 0.78), int(fw * 0.95), int(fh * 0.96)))
# Split into 5 feature icons roughly
sw, sh = strip.size
feature_dir_web = WEB / "features"
feature_dir_mob = MOB / "features"
feature_dir_web.mkdir(exist_ok=True)
feature_dir_mob.mkdir(exist_ok=True)
names = [
    "omnichannel",
    "ai-automation",
    "analytics",
    "team",
    "secure",
]
for i, name in enumerate(names):
    x0 = int(sw * i / 5)
    x1 = int(sw * (i + 1) / 5)
    cell = strip.crop((x0, 0, x1, sh))
    cell = trim(cell, 25)
    cell = punch_black_bg(cell, 40) if False else cell  # keep as-is from milky board
    out = square(cell, 256, (0, 0, 0, 0))
    out.save(feature_dir_web / f"{name}.png")
    out.save(feature_dir_mob / f"{name}.png")

for d in (WEB, MOB):
    mark.save(d / "relaysiq-mark.png")
    favicon.save(d / "relaysiq-favicon.png")
    app_icon.save(d / "relaysiq-app-icon.png")
    wordmark.save(d / "relaysiq-wordmark-dark.png")
    board.save(d / "relaysiq-brand-board.png")

# Android mipmaps from app icon
mipmaps = {
    "mipmap-mdpi": 48,
    "mipmap-hdpi": 72,
    "mipmap-xhdpi": 96,
    "mipmap-xxhdpi": 144,
    "mipmap-xxxhdpi": 192,
}
for folder, size in mipmaps.items():
    square(app_raw, size, (8, 12, 24, 255)).save(ANDROID / folder / "ic_launcher.png")

print("Installed high-res RelayIQ icons")
print("mark", (WEB / "relaysiq-mark.png").stat().st_size)
print("app", (WEB / "relaysiq-app-icon.png").stat().st_size)
print("features", list(feature_dir_web.iterdir()))
