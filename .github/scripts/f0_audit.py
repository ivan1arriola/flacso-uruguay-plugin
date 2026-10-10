#!/usr/bin/env python3
"""F0: inventario estático y dependencias aproximadas del plugin.

No importa ni ejecuta PHP, no consulta servicios ni lee valores de secretos.
Las declaraciones extraídas con regex son candidatas, no registros verificados
en WordPress. Los datos se guardan bajo --output, fuera del código fuente.
"""
from __future__ import annotations

import argparse
from collections import Counter, defaultdict
import csv
import json
from pathlib import Path
import re

IGNORED = {".git", "vendor", "node_modules", "build", ".venv", "coverage"}
ENTRY_CALLS = (
    "add_action", "add_filter", "add_shortcode", "register_rest_route",
    "register_post_type", "register_taxonomy", "register_meta",
    "register_post_meta", "register_activation_hook", "register_deactivation_hook",
    "wp_schedule_event", "wp_schedule_single_event", "wp_next_scheduled",
)
CONFIG_CALLS = (
    "get_option", "add_option", "update_option", "delete_option",
    "get_post_meta", "update_post_meta", "get_user_meta", "update_user_meta",
)
DEFINITIONS = re.compile(
    r"\b(?:(?:final|abstract)\s+)?(?:class|interface|trait)\s+([A-Za-z_]\w*)\b"
)
FUNCTIONS = re.compile(r"\bfunction\s+&?\s*([A-Za-z_]\w*)\s*\(")
PATH_RE = re.compile(r"['\"]((?:modules|includes)/[^'\"]+\.php)['\"]")
PHP_REQUIRE = re.compile(
    r"\b(?:include|include_once|require|require_once|flacso_safe_require|flacso_require)"
    r"\s*\(?\s*['\"]([^'\"]+\.php)['\"]", re.I,
)
CALL_RE = re.compile(
    r"\b(" + "|".join(ENTRY_CALLS + CONFIG_CALLS) + r")\s*\(", re.I
)
SIMPLE_ARG = re.compile(r"^\s*(['\"])(.*?)\1", re.S)
SQL_TABLE_RE = re.compile(
    r"\b(?:FROM|JOIN|INTO|UPDATE|TABLE(?:\s+IF\s+NOT\s+EXISTS)?)\s+"
    r"([A-Za-z_][A-Za-z_0-9.]*)", re.I
)


def module(path: str) -> str:
    parts = Path(path).parts
    if len(parts) > 1 and parts[0] == "modules":
        return parts[1]
    if parts[0] == "tests":
        return "tests"
    if parts[0] == "includes":
        return "shared"
    return "root"


def line_at(text: str, idx: int) -> int:
    return text.count("\n", 0, idx) + 1


def strip_comments(text: str) -> str:
    """Mitigación de falsos positivos; no es un lexer de PHP."""
    regex = re.compile(r"/\*[\s\S]*?\*/|(?<!:)//[^\n]*|(?m:^[ \t]*#[^\n]*)")
    return regex.sub(lambda m: re.sub(r"[^\n]", " ", m.group()), text)


def walk(root: Path):
    result = {}
    for file in sorted(root.rglob("*.php")):
        relative = file.relative_to(root)
        if set(relative.parts) & IGNORED:
            continue
        result[relative.as_posix()] = file.read_text(
            encoding="utf-8-sig", errors="replace"
        )
    return result


def add_item(collection, kind, value, file, source, position, confidence):
    collection.append({
        "kind": kind,
        "value": value,
        "module": module(file),
        "file": file,
        "line": line_at(source, position),
        "confidence": confidence,
    })


def detect(root: Path):
    sources = walk(root)
    symbols, entrypoints, dependencies, sql_candidates = [], [], [], []
    definitions = defaultdict(list)
    for file, content in sources.items():
        scan = strip_comments(content)
        for regex, kind in ((DEFINITIONS, "type"), (FUNCTIONS, "function")):
            for match in regex.finditer(scan):
                name = match.group(1)
                add_item(symbols, kind, name, file, scan, match.start(), "heuristic")
                if kind == "type":
                    definitions[name].append(file)
        for match in CALL_RE.finditer(scan):
            kind = match.group(1).lower()
            following = scan[match.end():match.end() + 320]
            literal = SIMPLE_ARG.match(following)
            value = literal.group(2) if literal else "<dynamic>"
            if kind == "register_rest_route" and literal:
                next_part = following[literal.end():]
                route = re.match(r"\s*,\s*(['\"])(.*?)\1", next_part, re.S)
                value += (route.group(2) if route else " / <dynamic>")
            add_item(
                entrypoints,
                kind,
                value,
                file,
                scan,
                match.start(),
                "literal-argument" if literal else "requires-runtime-review",
            )
        for match in re.finditer(r"\bWP_CLI::add_command\s*\(", scan):
            following = scan[match.end():match.end() + 180]
            arg = SIMPLE_ARG.match(following)
            add_item(entrypoints, "wp_cli", arg.group(2) if arg else "<dynamic>",
                     file, scan, match.start(), "literal-argument" if arg else "requires-runtime-review")
        for match in PHP_REQUIRE.finditer(scan):
            path = match.group(1)
            to_mod = module(path)
            if to_mod not in ("root", "tests"):
                dependencies.append({
                    "source_module": module(file), "target_module": to_mod,
                    "kind": "literal-require", "symbol": path,
                    "file": file, "line": line_at(scan, match.start()),
                    "confidence": "literal-path",
                })
        for match in PATH_RE.finditer(scan):
            path = match.group(1)
            to_mod = module(path)
            if to_mod not in ("root", "tests") and to_mod != module(file):
                dependencies.append({
                    "source_module": module(file), "target_module": to_mod,
                    "kind": "path-reference", "symbol": path,
                    "file": file, "line": line_at(scan, match.start()),
                    "confidence": "path-reference",
                })
        for match in SQL_TABLE_RE.finditer(scan):
            sql_candidates.append({
                "candidate": match.group(1), "file": file,
                "module": module(file), "line": line_at(scan, match.start()),
                "confidence": "sql-text-heuristic",
            })

    # Las referencias a clases son posibles acoplamientos, no invocaciones probadas.
    for name, locations in definitions.items():
        if len(name) < 8 or len(locations) != 1:
            continue
        owner = locations[0]
        owner_module = module(owner)
        if owner_module in ("root", "shared", "tests"):
            continue
        rx = re.compile(r"\b" + re.escape(name) + r"\b")
        for file, content in sources.items():
            if file == owner or module(file) == owner_module or module(file) == "tests":
                continue
            for m in list(rx.finditer(strip_comments(content)))[:20]:
                dependencies.append({
                    "source_module": module(file), "target_module": owner_module,
                    "kind": "class-name-reference", "symbol": name,
                    "file": file, "line": line_at(content, m.start()),
                    "confidence": "heuristic-usage",
                })

    file_index = [{
        "file": f, "module": module(f),
        "lines": source.count("\n") + 1,
        "bytes_utf8": len(source.encode("utf-8")),
    } for f, source in sources.items()]
    return file_index, symbols, entrypoints, dependencies, sql_candidates


def dependency_cycles(deps):
    edges = defaultdict(set)
    for dep in deps:
        a, b = dep["source_module"], dep["target_module"]
        if a != b and a not in ("tests",):
            edges[a].add(b)
    paths, cycles = set(), []
    def visit(n, chain):
        if n in chain:
            loop = chain[chain.index(n):] + [n]
            key = tuple(sorted(set(loop)))
            if key not in paths:
                paths.add(key)
                cycles.append(loop)
            return
        if len(chain) > 20:
            return
        for nxt in sorted(edges.get(n, ())):
            visit(nxt, chain + [n])
    for node in sorted(edges):
        visit(node, [])
    return cycles


def csv_out(path, columns, records):
    with path.open("w", encoding="utf-8", newline="") as output:
        writer = csv.DictWriter(output, fieldnames=columns)
        writer.writeheader()
        writer.writerows(records)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[2])
    parser.add_argument("--output", type=Path, default=Path("build/f0-baseline"))
    args = parser.parse_args()
    out = args.output.resolve()
    out.mkdir(parents=True, exist_ok=True)
    files, symbols, entries, deps, sql = detect(args.root.resolve())
    csv_out(out / "files.csv", ["file", "module", "lines", "bytes_utf8"], files)
    csv_out(out / "symbols.csv", ["kind", "value", "module", "file", "line", "confidence"], symbols)
    csv_out(out / "entrypoints.csv", ["kind", "value", "module", "file", "line", "confidence"], entries)
    csv_out(out / "dependencies.csv", ["source_module", "target_module", "kind", "symbol", "file", "line", "confidence"], deps)
    csv_out(out / "sql-candidates.csv", ["candidate", "file", "module", "line", "confidence"], sql)
    modules = sorted({f["module"] for f in files if f["module"] not in ("root", "shared", "tests")})
    hotspots = sorted(files, key=lambda f: f["lines"], reverse=True)[:15]
    types_duped = defaultdict(list)
    for s in symbols:
        if s["kind"] == "type":
            types_duped[s["value"]].append(s["file"])
    duplicates = {k: v for k, v in types_duped.items() if len(set(v)) > 1}
    cycles = dependency_cycles(deps)
    summary = {
        "scope": "Static source inspection; NOT runtime registrations or actual DB schemas",
        "files_php": len(files),
        "modules_with_php": modules,
        "by_module": dict(sorted(Counter(f["module"] for f in files).items())),
        "entrypoints_by_type": dict(sorted(Counter(e["kind"] for e in entries).items())),
        "entrypoints_dynamic": sum(1 for e in entries if e["value"] == "<dynamic>" or "<dynamic>" in e["value"]),
        "symbol_counts": dict(Counter(s["kind"] for s in symbols)),
        "dependency_edges_heuristic": len(deps),
        "candidate_cycles_heuristic": cycles[:40],
        "duplicate_declared_types_heuristic": duplicates,
        "hotspot_files": hotspots,
        "limitations": [
            "Regex matches may include text strings or inactive code; no full PHP AST.",
            "Class references and cycles are candidate dependencies, never proven calls.",
            "Dynamic hooks/routes/meta and permission callbacks need runtime verification.",
            "SQL keywords do not establish a live database table or schema.",
            "No production, credentials, data records or environment inspected."
        ],
    }
    (out / "summary.json").write_text(
        json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    lines = [
        "# F0 — Línea base estática", "",
        f"- Archivos PHP: **{len(files)}**",
        f"- Módulos con archivos PHP: **{len(modules)}**",
        f"- Candidatos de registro/hook/configuración: **{len(entries)}**",
        f"- Referencias de dependencia (heurísticas): **{len(deps)}**",
        f"- Ciclos potenciales (heurísticos): **{len(cycles)}**",
        f"- Declaraciones de tipos repetidas (heurísticas): **{len(duplicates)}**",
        "",
        "## Tipos de puntos de entrada", "",
        "| Tipo | Coincidencias |", "|---|---:|",
    ]
    lines += [f"| {kind} | {number} |" for kind, number in summary["entrypoints_by_type"].items()]
    lines += ["", "## Archivos de mayor tamaño (líneas)", "",
              "| Archivo | Líneas |", "|---|---:|"]
    lines += [f"| {f['file']} | {f['lines']} |" for f in hotspots]
    lines += ["", "## Limitaciones", ""]
    lines += ["- " + item for item in summary["limitations"]]
    lines += ["", "Artefactos: files.csv, symbols.csv, entrypoints.csv, dependencies.csv, sql-candidates.csv, summary.json.", ""]
    (out / "README.md").write_text("\n".join(lines), encoding="utf-8")
    print(f"F0 generated: {len(files)} PHP files; {len(entries)} entrypoint candidates; {len(deps)} dependency references")
    print(f"Potential cycles: {len(cycles)}; duplicate type names: {len(duplicates)}")
    print((out / "README.md").read_text(encoding="utf-8")[:5000])


if __name__ == "__main__":
    main()
