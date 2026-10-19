#!/usr/bin/env python3
"""
FLACSO API Client - Herramienta para que agentes de IA y desarrolladores
realicen consultas y cambios de contenido en vivo en WordPress (https://flacso.edu.uy).

Uso:
  python scripts/flacso_api.py whoami
  python scripts/flacso_api.py get /main-page/config
  python scripts/flacso_api.py post /main-page/config --json '{"hero":{"title":"..."}}'
  python scripts/flacso_api.py get /ofertas
  python scripts/flacso_api.py get /cohortes?parent_id=123
  python scripts/flacso_api.py patch /cohortes/456 --json '{"fecha_inicio":"2027-03-15"}'
  python scripts/flacso_api.py call GET /wp-json/wp/v2/pages
"""

from __future__ import annotations

import argparse
import base64
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

DEFAULT_BASE_URL = "https://flacso.edu.uy"
API_NAMESPACE = "/wp-json/flacso/v1"


def find_env_file() -> Path | None:
    """Busca un archivo .env en el plugin o en flacso-uruguay-editor."""
    curr = Path(__file__).resolve().parent.parent
    candidates = [
        curr / ".env",
        curr / ".env.local",
        curr.parent / "flacso-uruguay-editor" / ".env",
        curr.parent / "flacso-uruguay-editor" / ".env.local",
    ]
    for c in candidates:
        if c.is_file():
            return c
    return None


def load_credentials() -> tuple[str | None, str | None, str]:
    """Carga usuario, contraseña de aplicación y URL base de WordPress."""
    wp_user = os.environ.get("WP_USER")
    wp_password = os.environ.get("WP_PASSWORD")
    base_url = os.environ.get("WORDPRESS_URL", DEFAULT_BASE_URL).rstrip("/")

    env_path = find_env_file()
    if env_path and (not wp_user or not wp_password):
        for line in env_path.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if "=" in line:
                key, val = line.split("=", 1)
                key = key.strip()
                val = val.strip().strip("'\"")
                if key == "WP_USER" and not wp_user:
                    wp_user = val
                elif key == "WP_PASSWORD" and not wp_password:
                    wp_password = val
                elif key in ("WORDPRESS_URL", "NEXT_PUBLIC_WORDPRESS_URL") and base_url == DEFAULT_BASE_URL:
                    base_url = val.rstrip("/")

    return wp_user, wp_password, base_url


def api_request(
    method: str,
    endpoint: str,
    data: dict | list | None = None,
    wp_user: str | None = None,
    wp_password: str | None = None,
    base_url: str = DEFAULT_BASE_URL,
) -> tuple[int, dict | list | str]:
    """Ejecuta una petición HTTP contra la API REST de WordPress."""
    if endpoint.startswith("/wp-json/"):
        full_url = f"{base_url}{endpoint}"
    elif endpoint.startswith("/"):
        full_url = f"{base_url}{API_NAMESPACE}{endpoint}"
    else:
        full_url = f"{base_url}{API_NAMESPACE}/{endpoint}"

    headers = {
        "User-Agent": "FLACSO-Agent-Client/1.0",
        "Accept": "application/json",
    }

    if wp_user and wp_password:
        auth_bytes = f"{wp_user}:{wp_password}".encode("utf-8")
        headers["Authorization"] = f"Basic {base64.b64encode(auth_bytes).decode('ascii')}"

    body_bytes = None
    if data is not None:
        headers["Content-Type"] = "application/json"
        body_bytes = json.dumps(data).encode("utf-8")

    req = urllib.request.Request(
        url=full_url,
        data=body_bytes,
        headers=headers,
        method=method.upper(),
    )

    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            status = resp.status
            content = resp.read().decode("utf-8")
            try:
                parsed = json.loads(content)
            except Exception:
                parsed = content
            return status, parsed
    except urllib.error.HTTPError as e:
        status = e.code
        err_content = e.read().decode("utf-8")
        try:
            parsed = json.loads(err_content)
        except Exception:
            parsed = err_content
        return status, parsed
    except Exception as e:
        return 0, {"error": str(e)}


def cmd_whoami(wp_user: str | None, wp_password: str | None, base_url: str) -> int:
    print(f"Base URL: {base_url}")
    print(f"Usuario WP: {wp_user or 'No configurado'}")
    if not wp_user or not wp_password:
        print("[ERROR] Falta WP_USER o WP_PASSWORD en .env o entorno.")
        return 1

    print("Verificando autenticación con WordPress REST API...")
    status, res = api_request(
        method="GET",
        endpoint="/wp-json/wp/v2/users/me?context=edit",
        wp_user=wp_user,
        wp_password=wp_password,
        base_url=base_url,
    )

    if status == 200 and isinstance(res, dict):
        print(f"[OK] Conexión exitosa como: {res.get('name')} (ID: {res.get('id')})")
        print(f"Roles: {', '.join(res.get('roles', []))}")
        print(f"Capacidades administrativas: {res.get('capabilities', {}) != {}}")
        return 0
    else:
        print(f"[FALLO] HTTP {status}: {json.dumps(res, indent=2, ensure_ascii=False)}")
        return 1


def main() -> int:
    parser = argparse.ArgumentParser(description="Cliente REST de FLACSO para Agentes de IA")
    subparsers = parser.add_subparsers(dest="action")

    # whoami
    subparsers.add_parser("whoami", help="Verificar credenciales contra WordPress")

    # get
    get_p = subparsers.add_parser("get", help="Ejecutar GET sobre un endpoint")
    get_p.add_argument("endpoint", help="Ej: /ofertas, /main-page/config, /cohortes")

    # post
    post_p = subparsers.add_parser("post", help="Ejecutar POST sobre un endpoint")
    post_p.add_argument("endpoint", help="Ej: /main-page/config, /cohortes")
    post_p.add_argument("--json", dest="json_data", help="Payload en JSON string")

    # patch / put
    patch_p = subparsers.add_parser("patch", help="Ejecutar PATCH sobre un endpoint")
    patch_p.add_argument("endpoint", help="Ej: /cohortes/123, /ofertas/45")
    patch_p.add_argument("--json", dest="json_data", help="Payload en JSON string")

    # delete
    del_p = subparsers.add_parser("delete", help="Ejecutar DELETE sobre un endpoint")
    del_p.add_argument("endpoint", help="Ej: /cohortes/123")

    args = parser.parse_args()

    wp_user, wp_password, base_url = load_credentials()

    if not args.action:
        parser.print_help()
        return 0

    if args.action == "whoami":
        return cmd_whoami(wp_user, wp_password, base_url)

    method = args.action.upper()
    endpoint = getattr(args, "endpoint", "")

    payload = None
    if hasattr(args, "json_data") and args.json_data:
        try:
            payload = json.loads(args.json_data)
        except json.JSONDecodeError as exc:
            print(f"[ERROR] JSON inválido: {exc}")
            return 1

    status, res = api_request(
        method=method,
        endpoint=endpoint,
        data=payload,
        wp_user=wp_user,
        wp_password=wp_password,
        base_url=base_url,
    )

    if isinstance(res, (dict, list)):
        print(json.dumps(res, indent=2, ensure_ascii=False))
    else:
        print(res)

    if 200 <= status < 300:
        return 0
    return 1


if __name__ == "__main__":
    sys.exit(main())

