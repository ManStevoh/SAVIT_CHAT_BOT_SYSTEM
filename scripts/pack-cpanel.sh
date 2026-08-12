#!/usr/bin/env bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
SRC_DIR="$ROOT_DIR/LARAVEL_BACKEND"
ZIP_FILE="$ROOT_DIR/EssemChat-cPanel.zip"
STAGING_PARENT="$ROOT_DIR/_cpanel_staging"
STAGING_DIR="$STAGING_PARENT/LARAVEL_BACKEND"

echo "==> Production Packaging for cPanel <=="

cd "$SRC_DIR"

if [ -f "public/hot" ]; then
    rm -f "public/hot"
fi

echo "==> npm build..."
npm run build

if [ ! -f "public/build_fresh/manifest.json" ] && [ ! -f "public/build/manifest.json" ]; then
    echo "Error: manifest.json is missing!"
    exit 1
fi

echo "==> Preparing staging directory..."
rm -rf "$STAGING_PARENT"
mkdir -p "$STAGING_DIR"

echo "==> Copying application files to staging..."
python3 -c "
import os, shutil

src = '$SRC_DIR'
dst = '$STAGING_DIR'
has_fresh = os.path.exists(os.path.join(src, 'public', 'build_fresh'))

exclude_dirs = {
    'node_modules', '.git', 'tests', 'test-results', 'e2e', 'playwright-report', '.cursor',
    'assets_old', 'build_old', 'pint', 'fakerphp', 'phpunit', 'mockery', 'sebastian', 'phar-io',
    'theseer', 'myclabs', 'psysh', 'collision', 'ignition', 'flare-client-php', 'backtrace'
}
if has_fresh:
    exclude_dirs.add('build')

exclude_files = {'.env', '.env.local', '.env.backup', 'hot', '.phpunit.result.cache'}

for root, dirs, files in os.walk(src):
    dirs[:] = [d for d in dirs if d not in exclude_dirs]
    rel_path = os.path.relpath(root, src)
    
    target_rel = rel_path
    if has_fresh and (rel_path == 'public/build_fresh' or rel_path.startswith('public/build_fresh/')):
        target_rel = rel_path.replace('public/build_fresh', 'public/build', 1)
        
    target_root = os.path.join(dst, target_rel) if target_rel != '.' else dst
    os.makedirs(target_root, exist_ok=True)
    
    for file in files:
        if file in exclude_files:
            continue
        if rel_path == 'storage/logs' and file.endswith('.log'):
            continue
        src_file = os.path.join(root, file)
        dst_file = os.path.join(target_root, file)
        shutil.copy2(src_file, dst_file)
"

echo "==> Creating Zip Archive: $ZIP_FILE..."
rm -f "$ZIP_FILE"

python3 -c "
import os, zipfile
zip_path = '$ZIP_FILE'
staging_parent = '$STAGING_PARENT'

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zf:
    for root, dirs, files in os.walk(staging_parent):
        for file in files:
            full_path = os.path.join(root, file)
            arc_name = os.path.relpath(full_path, staging_parent)
            zf.write(full_path, arc_name)
"

rm -rf "$STAGING_PARENT"

SIZE_MB=$(python3 -c "import os; print(round(os.path.getsize('$ZIP_FILE') / (1024*1024), 2))")
echo "==> Done: $ZIP_FILE (${SIZE_MB} MB)"
echo "    Upload $ZIP_FILE to cPanel File Manager and extract."
