# GitHub Copilot Instructions - FLACSO Uruguay Plugin

Refer to `AGENTS.md` and `docs/agent-reference.md` for authoritative architecture rules.

## Key Invariants
- **Domain-driven structure**: Functional code must reside in `modules/<domain>/` (e.g. `oferta-academica`, `seminarios`, `main-page`, `consultas`). Never add code to `posgrados` or `shortcodes` (legacy).
- **PHP Compatibility**: Target PHP 7.4 to 8.x. Do not use PHP 8.1+ exclusive features (native enums, readonly classes/properties).
- **Encoding**: UTF-8 without BOM. No corrupt characters or mojibake.
- **SQL Security**: Always use `$wpdb->prepare()`. Never interpolate dynamic values directly into SQL strings.
- **WordPress Standards**: Sanitize inputs (`sanitize_text_field`, `absint`, `wp_kses_post`), escape outputs (`esc_html`, `esc_attr`, `esc_url`), verify nonces and `current_user_can()`.
- **Validation**: Always verify changes by running `python scripts/agent_check.py --diff`.
