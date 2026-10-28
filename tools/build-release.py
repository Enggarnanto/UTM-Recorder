"""Build a WordPress ZIP with portable entry paths, including on Windows.

Run from any directory: python tools/build-release.py
"""
from pathlib import Path, PurePosixPath
import re
import shutil
import tempfile
import zipfile


def build_release(root):
    source = root / "utm-traffic-recorder"
    main = source / "utm-traffic-recorder.php"
    match = re.search(r"^\s*\* Version: ([0-9.]+)\s*$", main.read_text(encoding="utf-8"), re.M)
    if not match:
        raise ValueError("Plugin version header missing")
    version = match.group(1)
    if f"define( 'UTR_VERSION', '{version}' );" not in main.read_text(encoding="utf-8"):
        raise ValueError("Plugin version constant does not match header")
    release = root / "release patch" / version
    release.mkdir(parents=True, exist_ok=True)
    target = release / f"utm-traffic-recorder-{version}.zip"
    files = sorted(path for path in source.rglob("*") if path.is_file())
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
        main_name = "utm-traffic-recorder/utm-traffic-recorder.php"
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
    shutil.copyfile(target, root / "utm-traffic-recorder.zip")
    for name in ("Code.gs", "SETUP.md"):
        shutil.copyfile(root / "integrations" / "google-sheets" / name, release / name)
    print(f"PASS: portable paths, exact WordPress entry point, CRC, extracted contents ({len(files)} files)")
    print(target)
    return target


if __name__ == "__main__":
    build_release(Path(__file__).resolve().parent.parent)
