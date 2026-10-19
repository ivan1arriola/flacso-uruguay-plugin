#!/usr/bin/env python3
"""Pruebas de contratos públicos mínimos y del generador F0.

Estas pruebas estáticas NO reemplazan tests en WordPress ni endpoints en staging.
"""
import re
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / ".github" / "scripts"))
from f0_audit import detect, dependency_cycles  # noqa: E402

EXPECTED_MODULES = (
    "core", "consultas", "docentes", "autoridades", "seminarios",
    "eventos", "convenios", "oferta-academica", "formularios",
    "charlas-abiertas", "posgrados", "shortcodes", "mailing",
    "preguntas-frecuentes", "preinscripciones", "main-page",
)
EXPECTED_CPTS = {
    "modules/docentes/includes/class-cpt-docente.php": "docente",
    "modules/oferta-academica/includes/class-cpt-oferta-academica.php": "oferta-academica",
    "modules/oferta-academica/includes/class-cohorte.php": "cohorte",
    "modules/seminarios/includes/class-edicion.php": "edicion",
}


def php(path):
    return (ROOT / path).read_text(encoding="utf-8-sig")


class PublicContractBaseline(unittest.TestCase):
    def test_registered_modules_are_stable(self):
        source = php("flacso-uruguay.php")
        modules = tuple(re.findall(r"\$loader->load_module\('([^']+)'\)", source))
        self.assertEqual(modules, EXPECTED_MODULES,
                         "Bootstrap changed: review module order and module contracts.")

    def test_stable_academic_post_type_identifiers(self):
        for path, slug in EXPECTED_CPTS.items():
            with self.subTest(path=path):
                source = php(path)
                self.assertRegex(source, r"POST_TYPE\s*=\s*['\"]" + re.escape(slug) + r"['\"]")

    def test_preinscriptions_public_api_contract(self):
        source = php("modules/preinscripciones/includes/class-preinscriptions-rest.php")
        self.assertRegex(source, r"NAMESPACE\s*=\s*['\"]flacso/v1['\"]")
        self.assertRegex(source, r"ROUTE\s*=\s*['\"]/preinscripciones['\"]")
        self.assertIn("register_rest_route", source)
        self.assertIn("permission_callback", source)

    def test_meta_webhook_has_signature_check_in_handler(self):
        source = php("includes/core/class-flacso-meta-leads-webhook.php")
        self.assertIn("register_rest_route", source)
        self.assertIn("is_valid_signature(", source)
        self.assertIn("invalid_signature", source)

    def test_inquiry_delivery_and_marketing_exist_separately(self):
        base = ROOT / "modules/consultas/services"
        for filename in (
            "class-flacso-inquiry-delivery-service.php",
            "class-flacso-inquiry-delivery-worker.php",
            "class-flacso-inquiry-marketing-service.php",
        ):
            with self.subTest(filename=filename):
                self.assertTrue((base / filename).is_file())
        for filename in (
            "inquiry-delivery-service-test.php",
            "inquiry-marketing-consent-test.php",
        ):
            self.assertTrue((ROOT / "tests" / filename).is_file())

    def test_declared_minimum_php_is_explicit(self):
        self.assertRegex(php("flacso-uruguay.php"), r"Requires PHP:\s*7\.4\b")


class InventoryGeneratorTests(unittest.TestCase):
    def test_extracts_literal_and_dynamic_entrypoints(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            folder = root / "modules" / "example"
            folder.mkdir(parents=True)
            (folder / "init.php").write_text(
                "<?php\n"
                "class ExampleThing {}\n"
                "add_action('init', 'handler');\n"
                "add_shortcode($name, 'handler');\n"
                "register_rest_route('example/v1', '/ping', array());\n",
                encoding="utf-8"
            )
            files, symbols, entries, _deps, _sql = detect(root)
            self.assertEqual(len(files), 1)
            self.assertTrue(any(s["value"] == "ExampleThing" for s in symbols))
            self.assertTrue(any(e["kind"] == "add_action" and e["value"] == "init"
                                for e in entries))
            self.assertTrue(any(e["kind"] == "register_rest_route" and e["value"] == "example/v1/ping"
                                for e in entries))
            self.assertTrue(any(e["kind"] == "add_shortcode" and e["value"] == "<dynamic>"
                                for e in entries))

    def test_cycle_detector_uses_module_graph(self):
        deps = [
            {"source_module": "a", "target_module": "b"},
            {"source_module": "b", "target_module": "a"},
            {"source_module": "c", "target_module": "b"},
        ]
        self.assertEqual(dependency_cycles(deps), [["a", "b"]])


if __name__ == "__main__":
    unittest.main()
