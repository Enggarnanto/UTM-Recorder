"""Buat potongan HTML Blogger dari landing page mandiri.

Jalankan: python buat-versi-blogger.py
"""

from __future__ import annotations

import re
from html.parser import HTMLParser
from pathlib import Path


HERE = Path(__file__).resolve().parent
SOURCE = HERE / "index.html"
OUTPUT = HERE / "blogger-page.html"
SCOPE = "#utr-doc-page"
LOGO_URL = (
    "https://blogger.googleusercontent.com/img/a/"
    "AVvXsEg012m6IdAyR0eUJzNipzATOwF3UGr41QT2KSOpKFWsC25o7BDkG9ZQtZSVLtOMkmMzFloboZWAM4xfKoqEu9wxMU96UvN_AJgR8jx50mz0aA3K8FzDKqBb3JMaszAyOlvkPKvIxIU-36mNRGpL4FsdJYm8BFJKMy0bUzxo_La4-_nb_QVM0t300rTiERgh=s150"
)


class IdCollector(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.ids: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        self.ids.extend(value for name, value in attrs if name == "id" and value)


def split_selectors(value: str) -> list[str]:
    parts: list[str] = []
    start = 0
    depth = 0
    quote = ""
    for index, char in enumerate(value):
        if quote:
            if char == quote and value[index - 1] != "\\":
                quote = ""
        elif char in "\"'":
            quote = char
        elif char in "([":
            depth += 1
        elif char in ")]":
            depth -= 1
        elif char == "," and depth == 0:
            parts.append(value[start:index].strip())
            start = index + 1
    parts.append(value[start:].strip())
    return [part for part in parts if part]


def scoped_selector(value: str) -> str:
    if value in {":root", "html", "body"}:
        return SCOPE
    if value == "*":
        return SCOPE + ", " + SCOPE + " *"
    return SCOPE + " " + value


def scope_css(css: str) -> str:
    """Scope ordinary CSS rules; recurse into media queries."""
    output: list[str] = []
    cursor = 0
    while cursor < len(css):
        opening = css.find("{", cursor)
        if opening < 0:
            output.append(css[cursor:])
            break
        header = css[cursor:opening].strip()
        depth = 1
        quote = ""
        index = opening + 1
        while index < len(css) and depth:
            char = css[index]
            if quote:
                if char == quote and css[index - 1] != "\\":
                    quote = ""
            elif char in "\"'":
                quote = char
            elif char == "{":
                depth += 1
            elif char == "}":
                depth -= 1
            index += 1
        if depth:
            raise ValueError("Kurung CSS tidak seimbang")
        inner = css[opening + 1 : index - 1]
        if header.startswith("@media") or header.startswith("@supports"):
            scoped_inner = scope_css(inner)
            output.append(header + "{\n" + scoped_inner + "}\n")
            size = re.fullmatch(r"@media\s*\(\s*max-width\s*:\s*(\d+px)\s*\)", header)
            if size:
                output.append("@container (max-width: " + size.group(1) + "){\n" + scoped_inner + "}\n")
        elif header.startswith("@"):
            output.append(header + "{" + inner + "}\n")
        else:
            selectors = ", ".join(scoped_selector(item) for item in split_selectors(header))
            output.append(selectors + "{" + inner + "}\n")
        cursor = index
    return "".join(output)


def main() -> None:
    source = SOURCE.read_text(encoding="utf-8")
    css = re.search(r"<style>(.*?)</style>", source, re.S)
    body = re.search(r"<body>(.*?)</body>", source, re.S)
    script = re.search(r"<script>(.*?)</script>", source, re.S)
    if not (css and body and script):
        raise ValueError("Struktur halaman sumber tidak ditemukan")

    content = re.sub(r"\s*<script>.*?</script>\s*$", "", body.group(1), flags=re.S).strip()
    logo = (
        '<span class="brand-mark" aria-hidden="true">'
        f'<img src="{LOGO_URL}" alt="" width="40" height="40" loading="eager" decoding="async">'
        "</span>"
    )
    content, logo_count = re.subn(
        r'<span class="brand-mark" aria-hidden="true"><svg.*?</svg></span>',
        lambda _: logo,
        content,
        flags=re.S,
    )
    if logo_count != 2:
        raise ValueError(f"Logo lama tidak ditemukan dua kali: {logo_count}")

    def replace_check_list(match: re.Match[str]) -> str:
        items = re.findall(r"<li>(.*?)</li>", match.group(1), flags=re.S)
        if not items:
            raise ValueError("Daftar fitur kosong")
        rows = "".join(
            '<div class="check-item" role="listitem">'
            '<span class="check-icon" aria-hidden="true"></span>'
            f"<span>{item}</span></div>"
            for item in items
        )
        return '<div class="check-list" role="list">' + rows + "</div>"

    content, list_count = re.subn(
        r'<ul class="check-list">(.*?)</ul>',
        replace_check_list,
        content,
        flags=re.S,
    )
    if list_count != 5:
        raise ValueError(f"Jumlah daftar fitur berubah: {list_count}")

    collector = IdCollector()
    collector.feed(content)
    ids = collector.ids
    for old in ids:
        new = "utr-" + old
        for attribute in ("id", "aria-controls", "aria-labelledby", "data-copy"):
            content = content.replace(f'{attribute}="{old}"', f'{attribute}="{new}"')
        content = content.replace(f'href="#{old}"', f'href="#{new}"')

    javascript = script.group(1)
    for old in ids:
        javascript = javascript.replace(f'getElementById("{old}")', f'getElementById("utr-{old}")')
    javascript = javascript.replace(
        '      const menuButton = ',
        '      const root = document.getElementById("utr-doc-page");\n'
        '      if (!root) return;\n'
        '      const menuButton = ',
        1,
    )
    javascript = javascript.replace("document.querySelectorAll(", "root.querySelectorAll(")
    javascript = javascript.replace("document.querySelector(link.hash)", "root.querySelector(link.hash)")
    javascript = javascript.replace(
        "document.documentElement.scrollHeight - window.innerHeight",
        "root.offsetHeight - window.innerHeight",
    )
    javascript = javascript.replace(
        "Math.min(1, window.scrollY / distance)",
        "Math.max(0, Math.min(1, -root.getBoundingClientRect().top / distance))",
    )

    styled = scope_css(css.group(1))
    styled = styled.replace('content:"✓"', 'content:""').replace('content:"−"', 'content:"-"')
    styled = (
        '@import url("https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:'
        'wght@400;500;600;700;800&family=Sora:wght@400;500;600;700;800&display=swap");\n'
        + styled
        + "\n#utr-doc-page{width:100%;max-width:100%;container-type:inline-size}\n"
        + "#utr-doc-page .brand-mark{background:transparent;overflow:hidden;flex:none}\n"
        + "#utr-doc-page .brand-mark img{width:100%;height:100%;object-fit:contain;display:block}\n"
        + "#utr-doc-page .check-item{display:flex;align-items:flex-start;gap:11px;font-size:.88rem}\n"
        + "#utr-doc-page .check-icon{position:relative;display:block;flex:0 0 22px;width:22px;height:22px;border-radius:7px;background:#e8f8c8}\n"
        + '#utr-doc-page .check-icon::after{content:"";position:absolute;left:8px;top:4px;width:6px;height:10px;border:solid #4a6602;border-width:0 2px 2px 0;transform:rotate(45deg)}\n'
        + "#utr-doc-page section[id], #utr-doc-page main[id]{scroll-margin-top:92px}\n"
    )
    result = (
        "<!-- UTM Traffic Recorder: tempel seluruh potongan ini di mode HTML halaman Blogger. -->\n"
        "<style>\n"
        + styled.strip()
        + "\n</style>\n"
        '<div id="utr-doc-page">\n'
        + content
        + "\n</div>\n"
        "<script>\n"
        + javascript.strip()
        + "\n</script>\n"
    )
    OUTPUT.write_text(result, encoding="utf-8")
    print(f"Dibuat: {OUTPUT} ({OUTPUT.stat().st_size} byte)")


if __name__ == "__main__":
    main()
