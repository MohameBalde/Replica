#!/usr/bin/env python3
"""Génère tokens.css et tailwind.tokens.js à partir de tokens.json.

    python3 replica/design/build_tokens.py

À relancer après chaque changement de tokens.json (par exemple après
/replica-brand), puis vérifier le contraste avec contrast.py.
"""
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))


def main():
    with open(os.path.join(HERE, "tokens.json"), encoding="utf-8") as fh:
        t = json.load(fh)

    css = ["/* Généré par build_tokens.py depuis tokens.json. Ne pas modifier à la main. */", ":root {"]
    for name, value in t["color"].items():
        css.append("  --color-%s: %s;" % (name, value))
    css.append("  --font-sans: %s;" % t["font"]["sans"])
    css.append("  --font-mono: %s;" % t["font"]["mono"])
    for name, spec in t["type"].items():
        if name.startswith("_"):
            continue
        css.append("  --text-%s: %spx;" % (name, spec["size"]))
        css.append("  --leading-%s: %spx;" % (name, spec["line"]))
        css.append("  --weight-%s: %s;" % (name, spec["weight"]))
    for i, px in enumerate(t["space"]):
        css.append("  --space-%d: %spx;" % (i, px))
    for name, px in t["radius"].items():
        css.append("  --radius-%s: %spx;" % (name, px))
    for name, value in t["shadow"].items():
        css.append("  --shadow-%s: %s;" % (name, value))
    for name, value in t["motion"].items():
        if not name.startswith("_"):
            css.append("  --motion-%s: %s;" % (name, value))
    for name, value in t["layout"].items():
        if isinstance(value, (int, float)):
            css.append("  --layout-%s: %spx;" % (name, value))
    css.append("}")
    css.append("@media (prefers-reduced-motion: reduce) {")
    css.append("  :root { --motion-fast: 0ms; --motion-base: 0ms; }")
    css.append("}")
    with open(os.path.join(HERE, "tokens.css"), "w", encoding="utf-8") as fh:
        fh.write("\n".join(css) + "\n")

    colors = {name: "var(--color-%s)" % name for name in t["color"]}
    font_size = {
        name: ["var(--text-%s)" % name,
               {"lineHeight": "var(--leading-%s)" % name, "fontWeight": "var(--weight-%s)" % name}]
        for name in t["type"] if not name.startswith("_")
    }
    theme = {
        "colors": dict(colors, transparent="transparent", current="currentColor"),
        "fontFamily": {"sans": ["var(--font-sans)"], "mono": ["var(--font-mono)"]},
        "fontSize": font_size,
        "borderRadius": dict({k: "var(--radius-%s)" % k for k in t["radius"]}, none="0"),
        "boxShadow": dict({k: "var(--shadow-%s)" % k for k in t["shadow"]}, none="none"),
        "screens": {k: "%spx" % v for k, v in t["layout"]["breakpoints"].items()},
        "extend": {
            "maxWidth": {"content": "var(--layout-content-max)", "admin": "var(--layout-admin-max)"},
            "minHeight": {"tap": "var(--layout-tap-min)"},
            "minWidth": {"tap": "var(--layout-tap-min)"},
            "height": {"header": "var(--layout-header)", "bottom-nav": "var(--layout-bottom-nav)"},
        },
    }
    js = ("// Généré par build_tokens.py depuis tokens.json. Ne pas modifier à la main.\n"
          "// Dans tailwind.config.js : theme: require('./replica/design/tailwind.tokens.js')\n"
          "module.exports = " + json.dumps(theme, indent=2, ensure_ascii=False) + ";\n")
    with open(os.path.join(HERE, "tailwind.tokens.js"), "w", encoding="utf-8") as fh:
        fh.write(js)
    print("tokens.css et tailwind.tokens.js générés")


if __name__ == "__main__":
    main()
