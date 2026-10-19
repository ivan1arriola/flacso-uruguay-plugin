#!/usr/bin/env python3
"""
Agent Check - Verificación integral para agentes de IA y desarrolladores.
Valida:
  1. Encoding: UTF-8 estricto sin BOM ni patrones de mojibake.
  2. Sintaxis PHP (php -l) en archivos PHP.
  3. Contratos de arquitectura F0 (Python unittest).
  4. Suites de prueba CLI en PHP (tests/*-test.php).

Uso:
  python scripts/agent_check.py            # Verificación completa
  python scripts/agent_check.py --diff     # Solo archivos modificados en git
  python scripts/agent_check.py --quick    # Encoding + Sintaxis PHP únicamente
"""

from __future__ import annotations

import argparse
import glob
import os
import re
import shutil
import subprocess
import sys
import unittest
from pathlib import Path

PLUGIN_ROOT = Path(__file__).resolve().parent.parent

EXCLUDED_DIRS = {
    ".git",
    ".githooks",
    ".github",
    ".superpowers",
    ".worktrees",
    "node_modules",
    "vendor",
    "dist",
    "build",
    ".idea",
    ".vscode",
    "__pycache__",
    ".venv",
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
    re.compile(r"\ufffd"),
]

BOM_SIGNATURES = {
    b"\xef\xbb\xbf": "UTF-8 BOM",
    b"\xff\xfe": "UTF-16 LE BOM",
    b"\xfe\xff": "UTF-16 BE BOM",
    b"\xff\xfe\x00\x00": "UTF-32 LE BOM",
    b"\x00\x00\xfe\xff": "UTF-32 BE BOM",
}


def find_php_binary() -> str | None:
    """Busca el ejecutable de PHP en PATH o en ubicaciones conocidas del sistema."""
    bin_in_path = shutil.which("php") or shutil.which("php.exe")
    if bin_in_path:
        return bin_in_path

    local_app_data = os.environ.get("LOCALAPPDATA", "")
    candidates = [
        os.path.join(local_app_data, "Programs", "php", "php.exe"),
        r"C:\tools\php\php.exe",
        r"C:\xampp\php\php.exe",
        r"C:\Program Files\PHP\php.exe",
    ]

    if local_app_data:
        temp_dir = os.path.join(local_app_data, "Temp")
        if os.path.isdir(temp_dir):
            for entry in os.listdir(temp_dir):
                if entry.startswith("flacso-php"):
                    candidates.append(os.path.join(temp_dir, entry, "php.exe"))

    for laragon_php in glob.glob(r"C:\laragon\bin\php\*\php.exe"):
        candidates.append(laragon_php)

    for c in candidates:
        if os.path.isfile(c) and os.access(c, os.X_OK):
            return c

    return None


def get_git_modified_files() -> list[Path]:
    """Obtiene los archivos modificados o staged en git."""
    try:
        res = subprocess.run(
            ["git", "status", "--porcelain"],
            cwd=PLUGIN_ROOT,
            capture_output=True,
            text=True,
            check=True,
        )
        files = []
        for line in res.stdout.splitlines():
            line = line.strip()
            if not line:
                continue
            parts = line.split(maxsplit=1)
            if len(parts) == 2:
                file_rel = parts[1].strip('"')
                p = PLUGIN_ROOT / file_rel
                if p.is_file():
                    files.append(p)
        return files
    except Exception:
        return []


def iterate_repo_files() -> list[Path]:
    """Recorre el árbol de archivos excluyendo directorios pesados/ignorados."""
    found = []
    for root, dirs, files in os.walk(PLUGIN_ROOT):
        dirs[:] = [d for d in dirs if d not in EXCLUDED_DIRS]
        for f in files:
            found.append(Path(root) / f)
    return found


def should_scan_encoding(path: Path) -> bool:
    suffix = path.suffix.lower()
    if suffix in BINARY_EXTENSIONS:
        return False
    if suffix in TEXT_EXTENSIONS:
        return True
    return suffix == "" and path.name.isascii()


def run_encoding_check(files_to_check: list[Path] | None = None) -> list[str]:
    """Verifica ausencia de BOM, UTF-8 válido y ausencia de mojibake."""
    issues = []
    file_list = files_to_check if files_to_check is not None else iterate_repo_files()

    for file_path in sorted(file_list):
        if not file_path.is_file():
            continue
        try:
            rel = file_path.relative_to(PLUGIN_ROOT)
        except ValueError:
            rel = file_path

        if not should_scan_encoding(rel):
            continue

        try:
            raw = file_path.read_bytes()
        except Exception as e:
            issues.append(f"{rel}: no se pudo leer ({e})")
            continue

        for sig, bom_name in BOM_SIGNATURES.items():
            if raw.startswith(sig):
                issues.append(f"{rel}: contiene {bom_name}. Debe ser UTF-8 sin BOM.")
                break

        try:
            text = raw.decode("utf-8")
        except UnicodeDecodeError as exc:
            issues.append(
                f"{rel}: UTF-8 inválido en byte {exc.start} (motivo: {exc.reason})"
            )
            continue

        for i, line in enumerate(text.splitlines(), start=1):
            for pattern in MOJIBAKE_PATTERNS:
                if pattern.search(line):
                    issues.append(
                        f"{rel}:{i}: posible carácter corrupto (mojibake) -> {line.strip()[:100]}"
                    )
                    break

    return issues


def run_php_lint(php_bin: str, files_to_check: list[Path] | None = None) -> tuple[int, list[str]]:
    """Ejecuta php -l en todos los archivos PHP seleccionados."""
    if files_to_check is None:
        all_files = iterate_repo_files()
        php_files = [p for p in all_files if p.suffix.lower() == ".php"]
    else:
        php_files = [p for p in files_to_check if p.suffix.lower() == ".php" and p.is_file()]

    errors = []
    for file_path in php_files:
        try:
            rel = file_path.relative_to(PLUGIN_ROOT)
        except ValueError:
            rel = file_path
        res = subprocess.run(
            [php_bin, "-l", str(file_path)],
            capture_output=True,
            text=True,
        )
        if res.returncode != 0:
            err_output = (res.stderr or res.stdout).strip()
            errors.append(f"{rel}: {err_output}")

    return len(php_files), errors


def run_f0_contracts() -> tuple[bool, str]:
    """Ejecuta los tests de contrato F0 en Python."""
    loader = unittest.TestLoader()
    suite = loader.discover(
        start_dir=str(PLUGIN_ROOT / "tests"),
        pattern="test_f0_*.py",
    )
    runner = unittest.TextTestRunner(verbosity=0, stream=open(os.devnull, "w"))
    result = runner.run(suite)
    if result.wasSuccessful():
        return True, f"Ejecutadas {result.testsRun} pruebas de contratos F0."
    return False, f"Fallaron {len(result.failures) + len(result.errors)} pruebas F0 de {result.testsRun}."


def run_php_contract_tests(php_bin: str) -> tuple[int, int, list[str]]:
    """Ejecuta las pruebas de contrato en PHP en tests/*.php."""
    test_files = sorted(PLUGIN_ROOT.glob("tests/*-test.php"))
    passed = 0
    failed = 0
    failures = []

    env = os.environ.copy()
    php_dir = str(Path(php_bin).parent)
    if php_dir not in env.get("PATH", ""):
        env["PATH"] = php_dir + os.path.pathsep + env.get("PATH", "")

    for t in test_files:
        rel = t.relative_to(PLUGIN_ROOT)
        try:
            res = subprocess.run(
                [php_bin, str(t)],
                cwd=PLUGIN_ROOT,
                capture_output=True,
                text=True,
                timeout=10,
                env=env,
            )
            if res.returncode == 0:
                passed += 1
            else:
                failed += 1
                out = (res.stderr or res.stdout).strip()
                first_line = out.splitlines()[0] if out else "Salida vacía con código de error"
                failures.append(f"{rel}: {first_line[:120]}")
        except subprocess.TimeoutExpired:
            failed += 1
            failures.append(f"{rel}: Timeout (>10s)")
        except Exception as e:
            failed += 1
            failures.append(f"{rel}: Excepción ({e})")

    return passed, failed, failures


def main() -> int:
    sys.stdout.reconfigure(line_buffering=True)
    parser = argparse.ArgumentParser(description="Verificador integral para agentes de IA")
    parser.add_argument("--diff", action="store_true", help="Validar únicamente archivos modificados en git")
    parser.add_argument("--quick", action="store_true", help="Solo verificar encoding y sintaxis PHP")
    parser.add_argument("--php", type=str, default=None, help="Ruta explícita al binario php")
    args = parser.parse_args()

    print("=" * 60)
    print(" FLACSO Uruguay Plugin - Verificación de Calidad para IA")
    print("=" * 60)

    files_subset = None
    if args.diff:
        files_subset = get_git_modified_files()
        print(f">> Modo diferencial: analizando {len(files_subset)} archivo(s) modificados en git.")
        if not files_subset:
            print("No hay archivos modificados en git para verificar.")
            return 0

    has_failures = False

    # 1. Encoding check
    print("\n[1/4] Comprobando codificación UTF-8, BOM y mojibake...")
    encoding_issues = run_encoding_check(files_subset)
    if encoding_issues:
        has_failures = True
        print(f"  [FALLO] Se encontraron {len(encoding_issues)} problema(s) de codificación:")
        for issue in encoding_issues[:10]:
            print(f"    - {issue}")
        if len(encoding_issues) > 10:
            print(f"    ... y {len(encoding_issues) - 10} más.")
    else:
        print("  [OK] Codificación limpia: UTF-8 válido, sin BOM ni mojibake.")

    # Localizar PHP
    php_bin = args.php or find_php_binary()

    # 2. PHP Syntax Lint
    print("\n[2/4] Validando sintaxis PHP (php -l)...")
    if not php_bin:
        print("  [AVISO] No se encontró el binario PHP. Se omite el lint de PHP.")
    else:
        count, lint_errors = run_php_lint(php_bin, files_subset)
        if lint_errors:
            has_failures = True
            print(f"  [FALLO] {len(lint_errors)} de {count} archivos PHP tienen errores de sintaxis:")
            for err in lint_errors[:10]:
                print(f"    - {err}")
        else:
            print(f"  [OK] {count} archivos PHP analizados sin errores de sintaxis.")

    # 3. Contratos F0 en Python
    print("\n[3/4] Validando contratos de arquitectura F0 (Python)...")
    f0_ok, f0_msg = run_f0_contracts()
    if f0_ok:
        print(f"  [OK] {f0_msg}")
    else:
        has_failures = True
        print(f"  [FALLO] {f0_msg}")

    # 4. Pruebas de contrato PHP
    if not args.quick:
        print("\n[4/4] Ejecutando suite de pruebas de contrato PHP...")
        if not php_bin:
            print("  [AVISO] Binario PHP no disponible para ejecutar la suite de pruebas.")
        else:
            p_count, f_count, p_failures = run_php_contract_tests(php_bin)
            if f_count > 0:
                has_failures = True
                print(f"  [FALLO] {f_count} pruebas fallaron (Pasadas: {p_count}):")
                for f in p_failures[:10]:
                    print(f"    - {f}")
                if len(p_failures) > 10:
                    print(f"    ... y {len(p_failures) - 10} más.")
            else:
                print(f"  [OK] Todas las {p_count} pruebas de contrato PHP pasaron con éxito.")
    else:
        print("\n[4/4] Suite de pruebas PHP omitida (--quick).")

    print("\n" + "=" * 60)
    if has_failures:
        print(" RESULTADO: [FALLO] Hay problemas pendientes antes de hacer commit/push.")
        print(" Revise los mensajes arriba y corrija los archivos afectados.")
        print("=" * 60)
        return 1
    else:
        print(" RESULTADO: [EXITO] Todos los chequeos pasaron correctamente.")
        print(" El plugin está íntegro y listo para ser utilizado o publicado.")
        print("=" * 60)
        return 0


if __name__ == "__main__":
    sys.exit(main())
