"""Build a WordPress ZIP with portable entry paths, including on Windows.

Run from any directory: python tools/build-release.py
"""
from pathlib import Path, PurePosixPath
import re
import shutil
import tempfile
import zipfile

PLUGIN_SLUG = 'utm-traffic-recorder'
PLUGIN_MAIN = PLUGIN_SLUG + '/utm-traffic-recorder.php'


def build_release(root):
    source = root / PLUGIN_SLUG
    main = source / "utm-traffic-recorder.php"
    match = re.search(r"^\s*\* Version: (\d+\.\d+\.\d+)\s*$", main.read_text(encoding="utf-8"), re.M)
    if not match:
        raise ValueError("Plugin version header missing")
    version = match.group(1)
    if f"define( 'UTR_VERSION', '{version}' );" not in main.read_text(encoding="utf-8"):
        raise ValueError("Plugin version constant does not match header")
    if not re.search(r'^Stable tag: ' + re.escape(version) + r'\s*$', (source / 'readme.txt').read_text(encoding='utf-8'), re.M):
        raise ValueError('Stable tag does not match the version header')
    release = root / "dist" / version
    release.mkdir(parents=True, exist_ok=True)
    target = release / f"utm-traffic-recorder-{version}.zip"
    files = sorted(path for path in source.rglob("*") if path.is_file())
    for path in files:
        if source.is_symlink() or path.is_symlink() or any(part.startswith('.') for part in path.relative_to(source).parts) or path.suffix.lower() in ('.log', '.zip', '.key', '.pem', '.tmp', '.bak', '.pyc') or (path.suffix.lower() == '.json' and path.name.lower().startswith(('credentials', 'service-account'))) or any(part in ('node_modules', 'vendor', '__pycache__') for part in path.relative_to(source).parts):
            raise ValueError(f'Unexpected runtime/private/build file in plugin source: {path.name}')
        if PLUGIN_SLUG in path.relative_to(source).parts:
            raise ValueError('Nested plugin root would create an invalid installation')
        if path.suffix.lower() == '.php' and path != main and re.search(r'^\s*\*?\s*Plugin Name:', path.read_text(encoding='utf-8'), re.M):
            raise ValueError('Multiple plugin entry points found in package')
    with zipfile.ZipFile(target, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in files:
            # Never pass a Windows path as the ZIP entry name.
            archive.write(path, arcname=path.relative_to(root).as_posix())

    with zipfile.ZipFile(target) as archive:
        names = archive.namelist()
        expected = {path.relative_to(root).as_posix() for path in files}
        if set(names) != expected or len(names) != len(expected):
            raise ValueError("ZIP file inventory does not match source")
        for name in names:
            posix = PurePosixPath(name)
            if "\\" in name or posix.is_absolute() or ".." in posix.parts or posix.parts[0] != source.name:
                raise ValueError(f"Nonportable ZIP entry: {name}")
        main_name = PLUGIN_MAIN
        if main_name not in names:
            raise ValueError("WordPress plugin entry point missing")
        if archive.testzip() is not None:
            raise ValueError("ZIP CRC check failed")
        with tempfile.TemporaryDirectory(prefix="utr-package-check-") as temporary:
            extracted = Path(temporary)
            archive.extractall(extracted)
            for path in files:
                if (extracted / path.relative_to(root)).read_bytes() != path.read_bytes():
                    raise ValueError(f"Extracted content mismatch: {path.name}")
    shutil.copyfile(target, root / "dist" / "utm-traffic-recorder.zip")
    for name in ("Code.gs", "SETUP.md"):
        shutil.copyfile(root / "integrations" / "google-sheets" / name, release / name)
    receiver = root / "integrations" / "webhook"
    if receiver.exists():
        with zipfile.ZipFile(release / "utm-webhook-receiver.zip", "w", compression=zipfile.ZIP_DEFLATED) as archive:
            for path in sorted(receiver.rglob("*")):
                if path.is_file():
                    archive.write(path, arcname="utm-webhook-receiver/" + path.relative_to(receiver).as_posix())
        shutil.copyfile(receiver / "README.md", release / "WEBHOOK-SETUP.md")
    print(f"PASS: portable paths, exact WordPress entry point, CRC, extracted contents ({len(files)} files)")
    print(target)
    return target


if __name__ == "__main__":
    build_release(Path(__file__).resolve().parent.parent)
