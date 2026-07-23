from PIL import Image, ImageDraw, ImageFont
from pathlib import Path

ROOT = Path(r"C:\SAVIT_CHAT_BOT")
SRC_ICON_PAIR = Path(
    r"C:\Users\Admin\AppData\Roaming\Cursor\User\workspaceStorage\empty-window\images"
    r"\ChatGPT Image Jul 23, 2026, 10_52_37 AM-48062558-6952-428d-9ede-1859a7fa4e9a.png"
)
SRC_WORDMARK = Path(
    r"C:\Users\Admin\AppData\Roaming\Cursor\User\workspaceStorage\empty-window\images"
    r"\ChatGPT Image Jul 23, 2026, 10_42_33 AM-136df3f0-4920-4f5e-87a6-c55e55ab6a96.png"
)
SRC_BOARD = Path(
    r"C:\Users\Admin\AppData\Roaming\Cursor\User\workspaceStorage\empty-window\images"
    r"\ChatGPT Image Jul 23, 2026, 10_42_50 AM-a1e0f1d7-dd96-48fb-87da-8f2e2e04fe90.png"
)

web_dir = ROOT / "LARAVEL_BACKEND" / "public" / "images" / "branding"
mobile_dir = ROOT / "MOBILE_APP" / "assets" / "branding"
web_dir.mkdir(parents=True, exist_ok=True)
mobile_dir.mkdir(parents=True, exist_ok=True)


def trim_to_content(im: Image.Image, bg_threshold: int = 22) -> Image.Image:
    rgba = im.convert("RGBA")
    pixels = rgba.load()
    w, h = rgba.size
    corners = [pixels[2, 2], pixels[w - 3, 2], pixels[2, h - 3], pixels[w - 3, h - 3]]
    bg = tuple(sum(c[i] for c in corners) // 4 for i in range(4))

    def is_bg(px):
        return all(abs(px[i] - bg[i]) <= bg_threshold for i in range(3))

    min_x, min_y, max_x, max_y = w, h, 0, 0
    found = False
    for y in range(h):
        for x in range(w):
            if not is_bg(pixels[x, y]):
                found = True
                min_x = min(min_x, x)
                min_y = min(min_y, y)
                max_x = max(max_x, x)
                max_y = max(max_y, y)
    if not found:
        return rgba
    pad = 6
    return rgba.crop(
        (
            max(0, min_x - pad),
            max(0, min_y - pad),
            min(w, max_x + pad + 1),
            min(h, max_y + pad + 1),
        )
    )


def make_square(im: Image.Image, size: int, fill=(0, 0, 0, 255)) -> Image.Image:
    im = im.convert("RGBA")
    im.thumbnail((size, size), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (size, size), fill)
    canvas.paste(im, ((size - im.width) // 2, (size - im.height) // 2), im)
    return canvas


# Split icon pair and drop bottom label band (~18%)
pair = Image.open(SRC_ICON_PAIR).convert("RGBA")
pw, ph = pair.size
label_cut = int(ph * 0.78)
left = pair.crop((0, 0, pw // 2, label_cut))
right = pair.crop((pw // 2, 0, pw, label_cut))

favicon = trim_to_content(left)
app_mark = trim_to_content(right)

favicon_sq = make_square(favicon, 512, fill=(0, 0, 0, 0))
# Keep dark squircle app icon
app_icon_sq = make_square(app_mark, 1024, fill=(8, 12, 24, 255))

# Transparent mark for light UI (favicon on transparent)
mark_transparent = make_square(favicon, 512, fill=(0, 0, 0, 0))

favicon_sq.save(web_dir / "relaysiq-favicon.png")
app_icon_sq.save(web_dir / "relaysiq-app-icon.png")
mark_transparent.save(web_dir / "relaysiq-mark.png")
favicon_sq.save(mobile_dir / "relaysiq-favicon.png")
app_icon_sq.save(mobile_dir / "relaysiq-app-icon.png")
mark_transparent.save(mobile_dir / "relaysiq-mark.png")

# Dark wordmark: drop bottom margin if any, keep full logo+tagline
wordmark_dark = Image.open(SRC_WORDMARK).convert("RGBA")
wordmark_dark = trim_to_content(wordmark_dark, bg_threshold=12)
wordmark_dark.save(web_dir / "relaysiq-wordmark-dark.png")
wordmark_dark.save(mobile_dir / "relaysiq-wordmark-dark.png")

# Light wordmark composed from mark + text for milky backgrounds
mark = make_square(favicon, 160, fill=(0, 0, 0, 0))
canvas = Image.new("RGBA", (720, 180), (0, 0, 0, 0))
canvas.paste(mark, (10, 10), mark)
draw = ImageDraw.Draw(canvas)
try:
    font = ImageFont.truetype("arialbd.ttf", 72)
    font_sm = ImageFont.truetype("arial.ttf", 22)
except OSError:
    font = ImageFont.load_default()
    font_sm = font
draw.text((190, 40), "Relay", fill=(17, 24, 39, 255), font=font)
# IQ in purple approximation
draw.text((190 + draw.textlength("Relay", font=font), 40), "IQ", fill=(124, 58, 237, 255), font=font)
draw.text((190, 120), "Every Conversation. Smarter.", fill=(107, 114, 128, 255), font=font_sm)
canvas = trim_to_content(canvas, bg_threshold=5)
canvas.save(web_dir / "relaysiq-wordmark-light.png")
canvas.save(mobile_dir / "relaysiq-wordmark-light.png")

# Keep board reference
Image.open(SRC_BOARD).save(web_dir / "relaysiq-brand-board.png")

# Android mipmaps from app icon
mipmaps = {
    "mipmap-mdpi": 48,
    "mipmap-hdpi": 72,
    "mipmap-xhdpi": 96,
    "mipmap-xxhdpi": 144,
    "mipmap-xxxhdpi": 192,
}
android_res = ROOT / "MOBILE_APP" / "android" / "app" / "src" / "main" / "res"
for folder, size in mipmaps.items():
    out = android_res / folder / "ic_launcher.png"
    make_square(app_mark, size, fill=(8, 12, 24, 255)).convert("RGBA").save(out, "PNG")
    print("wrote", out.name, size)

print("assets ready")
