#!/usr/bin/env python3
"""
Bazopardaz static site builder.

Why this exists: header, footer, <head> boilerplate, icons and scripts used to be pasted
into every page. Now they live once (src/partials, src/layout.html) and this script
assembles the final, plain static HTML files into ../public_html.

Output URLs, canonical URLs and folder layout are exactly the same as the live site,
so nothing about indexing changes. Needs only Python 3.8+, no packages.

Usage:   python3 src/build.py
"""
import hashlib
import html
import json
import pathlib
import re

SRC = pathlib.Path(__file__).resolve().parent
OUT = SRC.parent / "public_html"
SITE = json.loads((SRC / "site.json").read_text(encoding="utf-8"))
BASE = SITE["base"]
ORG_ID, SITE_ID = BASE + "/#organization", BASE + "/#website"
ROBOTS_DEFAULT = "index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1"


def esc(s):
    return html.escape(s, quote=True)


def read(p):
    return (SRC / p).read_text(encoding="utf-8")


def file_hash(rel):
    p = OUT / rel
    return hashlib.md5(p.read_bytes()).hexdigest()[:8] if p.exists() else "0"


def fill(tpl, **kw):
    for k, v in kw.items():
        tpl = tpl.replace("{{" + k + "}}", v)
    return tpl


def link(item, current_path, cls=""):
    cur = current_path == item["href"] or (item["href"] != "/" and current_path.startswith(item["href"]))
    attrs = f' class="{cls}"' if cls else ""
    attrs += ' aria-current="page"' if cur else ""
    return f'<a href="{item["href"]}"{attrs}>{item["label"]}</a>'


def load_pages():
    pages = []
    for f in sorted((SRC / "pages").rglob("*.src.html")):
        raw = f.read_text(encoding="utf-8")
        head, _, body = raw.partition("<!--/meta-->")
        meta = json.loads(head)
        meta["_body"] = body.strip() + "\n"
        pages.append(meta)
    return pages


def out_file(path):
    return OUT / (path.lstrip("/") + "index.html") if path.endswith("/") else OUT / path.lstrip("/")


def breadcrumb_html(crumbs):
    if not crumbs:
        return ""
    items = []
    for i, (label, href) in enumerate(crumbs):
        last = i == len(crumbs) - 1
        sep = '' if i == 0 else '<svg class="icon" width="14" height="14" aria-hidden="true" focusable="false"><use href="#i-chevron-left"/></svg>'
        if last:
            items.append(f'<li>{sep}<span aria-current="page">{label}</span></li>')
        else:
            items.append(f'<li>{sep}<a href="{href}">{label}</a></li>')
    return f'<div class="container"><nav class="breadcrumb" aria-label="مسیر ناوبری"><ol>{"".join(items)}</ol></nav></div>\n'


def json_ld(meta, url):
    nodes = []
    if meta["path"] == "/":
        nodes.append({
            "@type": "Organization", "@id": ORG_ID, "name": SITE["name"], "url": BASE + "/",
            "description": SITE["org_description"],
            "logo": {"@type": "ImageObject", "url": BASE + "/images/logo-512.png", "width": 512, "height": 512},
            "image": BASE + "/images/logo-512.png",
            "sameAs": [SITE["bale"], SITE["telegram"]],
            "contactPoint": {"@type": "ContactPoint", "contactType": "customer service",
                             "url": BASE + "/contact/", "availableLanguage": "Persian"},
        })
        nodes.append({"@type": "WebSite", "@id": SITE_ID, "name": SITE["name"], "url": BASE + "/",
                      "inLanguage": "fa-IR", "publisher": {"@id": ORG_ID}})
    page = {"@type": meta.get("ptype", "WebPage"), "@id": url + "#webpage", "url": url,
            "name": meta["title"], "description": meta["description"], "inLanguage": "fa-IR",
            "isPartOf": {"@id": SITE_ID}, "publisher": {"@id": ORG_ID}}
    crumbs = meta.get("crumbs")
    if crumbs:
        page["breadcrumb"] = {"@id": url + "#breadcrumb"}
    nodes.append(page)
    if crumbs:
        nodes.append({"@type": "BreadcrumbList", "@id": url + "#breadcrumb", "itemListElement": [
            {"@type": "ListItem", "position": i + 1, "name": c[0],
             **({"item": BASE + c[1]} if c[1] else {"item": url})} for i, c in enumerate(crumbs)]})
    # FAQPage is generated from the visible <details> FAQ, so markup and schema can never disagree
    faq = re.findall(r'<summary class="faq-trigger"><span>(.*?)</span>.*?</summary><div class="faq-answer"><div class="faq-answer-inner">(.*?)</div></div></details>',
                     meta["_body"], re.S)
    meta = dict(meta)
    meta["schema"] = [x for x in meta.get("schema", []) if x.get("@type") != "FAQPage"]
    if faq:
        strip = lambda t: html.unescape(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", "", t))).strip()
        nodes.append({"@type": "FAQPage", "@id": url + "#faq", "mainEntity": [
            {"@type": "Question", "name": strip(q), "acceptedAnswer": {"@type": "Answer", "text": strip(a)}} for q, a in faq]})
    for extra in meta.get("schema", []):
        extra = dict(extra)
        extra.pop("@context", None)
        t = extra.get("@type")
        if t in ("Organization", "WebSite", "BreadcrumbList"):
            continue  # defined once above, from site.json
        if t == "Service":
            extra["@id"] = url + "#service"
            extra["url"] = url
            extra["inLanguage"] = "fa-IR"
            extra["provider"] = {"@id": ORG_ID}
        nodes.append(extra)
    return '<script type="application/ld+json">' + json.dumps(
        {"@context": "https://schema.org", "@graph": nodes}, ensure_ascii=False, separators=(",", ":")) + "</script>\n"


def social_tags(meta, url):
    img = BASE + meta.get("og_image", SITE["default_og"])
    alt = meta.get("og_image_alt", SITE["default_og_alt"])
    w, h = meta.get("og_size", [1200, 630]) if "og_image" not in meta else meta.get("og_size", [1200, 630])
    t, d = esc(meta["title"]), esc(meta["description"])
    return (
        f'<meta property="og:type" content="{meta.get("og_type", "website")}">\n'
        f'<meta property="og:site_name" content="{SITE["name"]}">\n<meta property="og:locale" content="fa_IR">\n'
        f'<meta property="og:title" content="{t}">\n<meta property="og:description" content="{d}">\n'
        f'<meta property="og:url" content="{url}">\n<meta property="og:image" content="{img}">\n'
        f'<meta property="og:image:width" content="{w}">\n<meta property="og:image:height" content="{h}">\n'
        f'<meta property="og:image:alt" content="{esc(alt)}">\n'
        f'<meta name="twitter:card" content="summary_large_image">\n<meta name="twitter:title" content="{t}">\n'
        f'<meta name="twitter:description" content="{d}">\n<meta name="twitter:image" content="{img}">\n'
        f'<meta name="twitter:image:alt" content="{esc(alt)}">\n')


def build():
    layout = read("layout.html")
    icons = read("partials/icons.html")
    header_t, footer_t = read("partials/header.html"), read("partials/footer.html")
    css_v, js_v = file_hash("css/style.css"), None
    js_all = "".join((OUT / "js" / n).read_bytes().decode("utf-8") for n in ("site.js",))
    js_v = hashlib.md5(js_all.encode()).hexdigest()[:8]
    pages = load_pages()
    urls = []
    for meta in pages:
        path = meta["path"]
        url = BASE + path
        noindex = "noindex" in meta.get("robots", "")
        nav_links = "".join(link(i, path) for i in SITE["nav"])
        mobile_links = "".join(link(i, path) for i in SITE["nav"])
        header = fill(header_t, nav_links=nav_links, mobile_links=mobile_links,
                      cta_current=' aria-current="page"' if path == "/contact/" else "",
                      bale=SITE["bale"], telegram=SITE["telegram"])
        footer = fill(footer_t,
                      footer_services="".join(link(i, path) for i in SITE["footer_services"]),
                      footer_links="".join(link(i, path) for i in SITE["footer_links"]),
                      bale=SITE["bale"], telegram=SITE["telegram"], year_fa=SITE["year_fa"])
        css = "".join(f'<link rel="stylesheet" href="/css/pages/{n}.css?v={file_hash(f"css/pages/{n}.css")}">\n'
                      for n in meta.get("css", []))
        js = "".join(f'<script src="/js/{n}.js?v={file_hash(f"js/{n}.js")}" defer></script>\n'
                     for n in meta.get("scripts", []))
        body_cls = " ".join(filter(None, [meta.get("body_class", ""), "no-cta-bar" if meta.get("hide_cta_bar") else ""]))
        doc = fill(layout,
                   title=esc(meta["title"]), description=esc(meta["description"]),
                   robots=ROBOTS_DEFAULT if meta.get("robots") in (None, "index, follow") else meta["robots"],
                   canonical_tag="" if noindex else f'<link rel="canonical" href="{url}">\n',
                   social_tags="" if noindex else social_tags(meta, url),
                   css_v=css_v, js_v=js_v, page_css=css,
                   json_ld="" if noindex else json_ld(meta, url),
                   body_class=f' class="{body_cls}"' if body_cls else "",
                   icons=icons, header=header, footer=footer,
                   breadcrumb=breadcrumb_html(meta.get("crumbs")), content=meta["_body"], page_js=js)
        dest = out_file(path)
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_text(doc, encoding="utf-8", newline="\n")
        if not noindex and meta.get("sitemap", True):
            urls.append((url, meta.get("lastmod", SITE["lastmod"])))
    # sitemap: honest lastmod only (Google ignores priority / changefreq)
    order = {"/": 0}
    urls.sort(key=lambda u: (order.get(u[0][len(BASE):], 1), u[0]))
    sm = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
    for u, lm in urls:
        sm.append(f"  <url><loc>{u}</loc><lastmod>{lm}</lastmod></url>")
    sm.append("</urlset>\n")
    (OUT / "sitemap.xml").write_text("\n".join(sm), encoding="utf-8", newline="\n")
    print(f"built {len(pages)} pages, {len(urls)} in sitemap")


if __name__ == "__main__":
    build()
