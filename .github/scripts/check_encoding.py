#!/usr/bin/env python3
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path.cwd()

EXCLUDED_DIR_NAMES = {
    ".git",
    ".svn",
    ".hg",
    "node_modules",
    "vendor",
    "dist",
    "build",
    ".idea",
    ".vscode",
}

BINARY_EXTENSIONS = {
    ".png", ".jpg", ".jpeg", ".gif", ".webp", ".svg", ".ico", ".bmp", ".tiff",
    ".pdf", ".zip", ".gz", ".tar", ".7z", ".rar", ".phar",
    ".mp3", ".wav", ".ogg", ".mp4", ".mov", ".avi", ".mkv",
    ".woff", ".woff2", ".ttf", ".otf", ".eot",
    ".exe", ".dll", ".so", ".dylib", ".class", ".jar",
    ".lock",
}

TEXT_EXTENSIONS = {
    ".php", ".phtml", ".inc",
    ".js", ".jsx", ".ts", ".tsx", ".mjs", ".cjs",
    ".css", ".scss", ".sass", ".less",
    ".html", ".htm", ".xml",
    ".json", ".yml", ".yaml", ".toml", ".ini", ".cfg",
    ".md", ".txt", ".csv", ".sql",
    ".sh", ".bash", ".zsh", ".ps1", ".bat",
    ".gitignore", ".gitattributes", ".editorconfig",
}

MOJIBAKE_PATTERNS = [
    re.compile(r"Ã[\x80-\xBF]"),
    re.compile(r"Â[\x80-\xBF]"),
    re.compile(r"â€™|â€œ|â€\x9d|â€“|â€”|â€¦|â‚¬|â„¢"),
    re.compile(r"ï»¿"),
    re.compile(r"�"),
]

BOM_SIGNATURES = {
    b"\xef\xbb\xbf": "UTF-8 BOM",
    b"\xff\xfe": "UTF-16 LE BOM",
    b"\xfe\xff": "UTF-16 BE BOM",
    b"\xff\xfe\x00\x00": "UTF-32 LE BOM",
    b"\x00\x00\xfe\xff": "UTF-32 BE BOM",
}


def is_excluded(path: Path) -> bool:
    return any(part in EXCLUDED_DIR_NAMES for part in path.parts)


def should_scan(path: Path) -> bool:
    if is_excluded(path):
        return False

    suffix = path.suffix.lower()
    if suffix in BINARY_EXTENSIONS:
        return False

    if suffix in TEXT_EXTENSIONS:
        return True

    # Also scan common root files without extension (e.g., Dockerfile, LICENSE).
    return suffix == "" and path.name.isascii()


def detect_bom(raw: bytes) -> str | None:
    for signature, name in BOM_SIGNATURES.items():
        if raw.startswith(signature):
            return name
    return None


def main() -> int:
    issues: list[str] = []

    for file_path in sorted(ROOT.rglob("*")):
        if not file_path.is_file():
            continue
        if not should_scan(file_path.relative_to(ROOT)):
            continue

        rel = file_path.relative_to(ROOT)
        raw = file_path.read_bytes()

        bom = detect_bom(raw)
        if bom:
            issues.append(f"{rel}: contains {bom}")

        try:
            text = raw.decode("utf-8")
        except UnicodeDecodeError as exc:
            issues.append(
                f"{rel}: invalid UTF-8 at byte {exc.start} (reason: {exc.reason})"
            )
            continue

        for i, line in enumerate(text.splitlines(), start=1):
            for pattern in MOJIBAKE_PATTERNS:
                if pattern.search(line):
                    issues.append(
                        f"{rel}:{i}: possible mojibake -> {line.strip()[:140]}"
                    )
                    break

    if issues:
        print("Encoding check failed. Found issues:\n")
        for issue in issues:
            print(f"- {issue}")
        return 1

    print("Encoding check passed. No BOM, UTF-8 errors, or mojibake patterns found.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
