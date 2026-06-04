#!/usr/bin/env python3
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path.cwd()
PLUGIN_FILE = ROOT / "flacso-uruguay.php"

HEADER_PATTERN = re.compile(
    r"(^\s*\*\s*Version:\s*)(\d+)\.(\d+)\.(\d+)(\s*$)",
    re.MULTILINE,
)
DEFINE_PATTERN = re.compile(
    r"(define\('FLACSO_URUGUAY_VERSION',\s*')(\d+)\.(\d+)\.(\d+)('\);)"
)


def fail(message: str) -> int:
    print(f"[pre-commit] ERROR: {message}")
    return 1


def normalize_version(version_match: tuple[str, str, str]) -> tuple[int, int, int]:
    major, minor, patch = (int(part) for part in version_match)

    if patch >= 10:
        minor += patch // 10
        patch %= 10

    if minor >= 10:
        major += minor // 10
        minor %= 10

    return major, minor, patch


def bump_patch(version_match: tuple[str, str, str]) -> tuple[int, int, int]:
    major, minor, patch = normalize_version(version_match)
    patch += 1
    return normalize_version((str(major), str(minor), str(patch)))


def main() -> int:
    if not PLUGIN_FILE.exists():
        return fail(f"No se encontro {PLUGIN_FILE}")

    content = PLUGIN_FILE.read_text(encoding="utf-8")

    header_match = HEADER_PATTERN.search(content)
    if not header_match:
        return fail("No se pudo leer '* Version:' en flacso-uruguay.php")

    define_match = DEFINE_PATTERN.search(content)
    if not define_match:
        return fail("No se pudo leer define('FLACSO_URUGUAY_VERSION', ...) en flacso-uruguay.php")

    current_header_version = ".".join(header_match.group(i) for i in range(2, 5))
    current_define_version = ".".join(define_match.group(i) for i in range(2, 5))

    if current_header_version != current_define_version:
        print(
            "[pre-commit] WARNING: Versiones desincronizadas detectadas "
            f"(header={current_header_version}, define={current_define_version})."
        )

    normalized_major, normalized_minor, normalized_patch = normalize_version(
        header_match.group(2, 3, 4)
    )
    normalized_version = f"{normalized_major}.{normalized_minor}.{normalized_patch}"

    if normalized_version != current_header_version:
        print(
            "[pre-commit] INFO: Version normalizada con acarreo "
            f"({current_header_version} -> {normalized_version})."
        )

    next_major, next_minor, next_patch = bump_patch(header_match.group(2, 3, 4))
    next_version = f"{next_major}.{next_minor}.{next_patch}"

    updated = HEADER_PATTERN.sub(
        rf"\g<1>{next_major}.{next_minor}.{next_patch}\g<5>",
        content,
        count=1,
    )
    updated = DEFINE_PATTERN.sub(
        rf"\g<1>{next_major}.{next_minor}.{next_patch}\g<5>",
        updated,
        count=1,
    )

    if updated == content:
        return fail("No se aplico ningun cambio de version.")

    PLUGIN_FILE.write_text(updated, encoding="utf-8")
    print(f"[pre-commit] Plugin version: {current_header_version} -> {next_version}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
