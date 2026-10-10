#!/usr/bin/env python3
"""Inventario estático, reproducible, sin ejecutar PHP ni cargar WordPress.

La extracción es heurística: las coincidencias dinámicas se marcan como tales.
No revela valores de configuración ni secretos.
"""
from __future__ import annotations
import argparse
import collections
import csv
import json
import pathlib
import re
import subprocess

EXCLUDED = {".git", "vendor", "node_modules", ".venv", "coverage"}
PATTERNS = {
    "class": r"\b(?:abstract\s+|final\s+)?class\s+([A-Za-z_][\w]*)",
    "interface": r"\binterface\s+([A-Za-z_][\w]*)",
    "trait": r"\btrait\s+([A-Za-z_][\w]*)",
    "function": r"\bfunction\s+&?\s*([A-Za-z_][\w]*)\s*\(",
    "action": r"\badd_action\s*\(\s*(['\"])(.*?)\1",
    "filter": r"\badd_filter\s*\(\s*(['\"])(.*?)\1",
    "shortcode": r"\badd_shortcode\s*\(\s*(['\"])(.*?)\1",
    "rest_namespace_route": r"\bregister_rest_route\s*\(\s*(['\"])(.*?)\1\s*,\s*(['\"])(.*?)\3",
    "post_type": r"\bregister_post_type\s*\(\s*(['\"])(.*?)\1",
    "taxonomy": r"\bregister_taxonomy\s*\(\s*(['\"])(.*?)\1",
    "meta": r"\b(?:register_post_meta|register_meta)\s*\(\s*(['\"])(.*?)\1\s*,\s*(['\"])(.*?)\3",
    "option": r"\b(?:get_option|update_option|add_option|delete_option)\s*\(\s*(['\"])(.*?)\1",
    "cron": r"\b(?:wp_schedule_event|wp_schedule_single_event|wp_next_scheduled|wp_clear_scheduled_hook)\s*\(",
    "sql": r"\b(?:SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|CREATE\s+TABLE|ALTER\s+TABLE)\b",
    "require": r"\b(?:require|require_once|include|include_once)\s*(?:\(\s*)?([^;\n]+)",
}
COMPILED = {k: re.compile(v, re.I | re.S if k == "rest_namespace_route" else re.I) for k, v in PATTERNS.items()}
VALUE_FIELDS = {"action": 2, "filter": 2, "shortcode": 2, "post_type": 2, "taxonomy": 2, "option": 2}
NAMES = {"class", "interface", "trait", "function"}
DYNAMIC = re.compile(r"\b(add_action|add_filter|add_shortcode|register_rest_route|register_post_type|register_taxonomy|register_meta|register_post_meta)\s*\(\s*(?!['\"])", re.I)


def mask_comments(source: str) -> str:
    # Conserva posiciones y saltos de línea; reduce falsos positivos en comentarios.
    regex = re.compile(r"/\*[\s\S]*?\*/|//[^\n]*|#[^\n]*")
    return regex.sub(lambda m: re.sub(r"[^\n]", " ", m.group()), source)


def module_for(path: str) -> str:
    parts = pathlib.PurePosixPath(path).parts
    if len(parts) >= 2 and parts[0] == "modules":
        return parts[1]
    if parts[0] == "tests":
        return "tests"
    if parts[0] == "includes":
        return "shared"
    return "root"


def generate(root: pathlib.Path):
    records = []
    files = []
    for path in sorted(root.rglob("*.php")):
        rel = path.relative_to(root)
        if any(part in EXCLUDED for part in rel.parts):
            continue
        name = rel.as_posix()
        source = path.read_text(encoding="utf-8-sig", errors="replace")
        clean = mask_comments(source)
        files.append({"path": name, "module": module_for(name), "lines": source.count("\n") + 1, "bytes": path.stat().st_size})
        for kind, regex in COMPILED.items():
            for m in regex.finditer(clean):
                if kind == "rest_namespace_route":
                    value = m.group(2) + m.group(4)
                elif kind in VALUE_FIELDS:
                    value = m.group(VALUE_FIELDS[kind])
                elif kind == "require":
                    # Expression only, never resolve or evaluate file paths.
                    value = re.sub(r"\s+", " ", m.group(1).strip())[:200]
                elif kind == "sql":
                    value = m.group(0).upper()
                elif kind == "cron":
                    value = m.group(0).split("(")[0]
                else:
                    value = m.group(1)
                records.append({"kind": kind, "value": value, "file": name, "line": clean.count("\n", 0, m.start()) + 1, "module": module_for(name), "confidence": "static-heuristic"})
        for m in DYNAMIC.finditer(clean):
            records.append({"kind": "dynamic-registration", "value": m.group(1), "file": name, "line": clean.count("\n", 0, m.start()) + 1, "module": module_for(name), "confidence": "requires-review"})
    return files, records


def write_csv(path, rows, keys):
    with path.open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=keys)
        writer.writeheader()
        writer.writerows(rows)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=pathlib.Path, default=pathlib.Path(__file__).resolve().parents[2])
    parser.add_argument("--output", type=pathlib.Path, default=pathlib.Path("build/code-inventory"))
    args = parser.parse_args()
    root = args.root.resolve()
    out = args.output.resolve()
    out.mkdir(parents=True, exist_ok=True)
    files, records = generate(root)
    summary = {
        "php_files": len(files),
        "modules": sorted({f["module"] for f in files if f["module"] not in {"root", "shared", "tests"}}),
        "by_kind": dict(sorted(collections.Counter(r["kind"] for r in records).items())),
        "by_module": dict(sorted(collections.Counter(f["module"] for f in files).items())),
        "limitations": [
            "Regex-based static extraction, not an AST parser or execution trace.",
            "Dynamic hook names, SQL tables, WordPress values and indirect registrations require manual review.",
            "Values in PHP string literals can create false positives; no secrets or option values are read.",
            "Counts represent matching declarations/calls, not necessarily distinct runtime registrations.",
        ],
    }
    write_csv(out / "files.csv", files, ["path", "module", "lines", "bytes"])
    write_csv(out / "symbols.csv", records, ["kind", "value", "file", "line", "module", "confidence"])
    (out / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    lines = ["# Inventario estático del plugin", "",
             f"- Archivos PHP: **{len(files)}**",
             f"- Módulos detectados: **{len(summary['modules'])}**", "",
             "## Registros por categoría", "",
             "| Categoría | Coincidencias |", "|---|---:|"]
    lines += [f"| {kind} | {number} |" for kind, number in summary["by_kind"].items()]
    lines += ["", "## Archivos por módulo", "", "| Módulo | Archivos PHP |", "|---|---:|"]
    lines += [f"| {mod} | {number} |" for mod, number in summary["by_module"].items()]
    lines += ["", "## Limitaciones", ""]
    lines += ["- " + x for x in summary["limitations"]]
    lines += ["", "Ver `files.csv` para inventario de archivos y `symbols.csv` para coincidencias con archivo y línea."]
    (out / "README.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"Inventario: {len(files)} PHP files, {len(records)} detections. Output: {out}")


if __name__ == "__main__":
    main()
