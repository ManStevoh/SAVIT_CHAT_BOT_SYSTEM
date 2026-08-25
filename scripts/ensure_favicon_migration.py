import sqlite3

db = r"C:\SAVIT_CHAT_BOT\LARAVEL_BACKEND\database\database.sqlite"
c = sqlite3.connect(db)
cols = [r[1] for r in c.execute("PRAGMA table_info(platform_settings)")]
print("has app_favicon:", "app_favicon" in cols)
print("branding cols:", [x for x in cols if "logo" in x or "fav" in x])
rows = list(
    c.execute(
        "SELECT migration FROM migrations WHERE migration LIKE '%favicon%' OR migration LIKE '%2026_07_25%'"
    )
)
print("migration rows:", rows)
if "app_favicon" in cols and not any("2026_07_25_100000" in r[0] for r in rows):
    c.execute(
        "INSERT INTO migrations (migration, batch) VALUES (?, (SELECT COALESCE(MAX(batch),0)+1 FROM migrations))",
        ("2026_07_25_100000_add_app_favicon_to_platform_settings",),
    )
    c.commit()
    print("migration recorded")
else:
    print("no insert needed")
c.close()
