"""Arnes de pruebas del checkout en DEV (simula a un cliente: sesion, carrito, checkout). Ver README.md.
Solo dev.delpuente.com.gt. Crea pedidos reales de prueba: cancelarlos despues (estado cancelled)."""
import urllib.request, urllib.parse, http.cookiejar, re, json, sys, time
from html.parser import HTMLParser
BASE = "https://dev.delpuente.com.gt"

class Form(HTMLParser):
    def __init__(self):
        super().__init__(); self.f = {}; self.inform = False; self.sel = None; self.opts = []
    def handle_starttag(self, tag, a):
        a = dict(a)
        if tag == "form" and "checkout" in (a.get("class") or ""): self.inform = True
        if not self.inform: return
        if tag == "input":
            n = a.get("name"); t = (a.get("type") or "text").lower()
            if not n: return
            if t in ("checkbox", "radio"):
                if "checked" in a: self.f[n] = a.get("value", "1")
            elif t == "submit" or t == "button": pass
            else: self.f[n] = a.get("value", "")
        elif tag == "select":
            self.sel = a.get("name"); self.opts = []; self.selval = None
        elif tag == "option" and self.sel:
            if "selected" in a: self.selval = a.get("value", "")
            self.opts.append(a.get("value", ""))
        elif tag == "textarea":
            n = a.get("name"); 
            if n: self.f[n] = ""
    def handle_endtag(self, tag):
        if tag == "select" and self.sel:
            self.f[self.sel] = self.selval if self.selval is not None else (self.opts[0] if self.opts else "")
            self.sel = None

class S:
    def __init__(self, logged=None):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))
        self.op.addheaders = [("User-Agent", "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) DLPTest/1.0")]
        if logged:
            name, val = logged
            c = http.cookiejar.Cookie(0, name, val, None, False, "dev.delpuente.com.gt", True, False, "/", True, False, None, False, None, None, {})
            self.cj.set_cookie(c)
    def get(self, path):
        return self.op.open(BASE + path, timeout=60).read().decode("utf8", "replace")
    def post(self, path, data):
        body = urllib.parse.urlencode(data).encode()
        try:
            r = self.op.open(urllib.request.Request(BASE + path, data=body, headers={"X-Requested-With": "XMLHttpRequest"}), timeout=90)
            return r.read().decode("utf8", "replace")
        except urllib.error.HTTPError as e:
            return e.read().decode("utf8", "replace")
    def add(self, pid, qty=1, extra=None):
        d = {"product_id": pid, "quantity": qty}  # NO mandar tambien "add-to-cart": duplica la cantidad
        d.update(extra or {})
        return self.post("/?wc-ajax=add_to_cart", d)
    def checkout_page(self):
        h = self.get("/finalizar-compra/")
        p = Form(); p.feed(h)
        m = re.search(r"var DLP_CHECKOUT\s*=\s*(\{.*?\});", h, re.S)
        cfg = json.loads(m.group(1)) if m else {}
        return p.f, cfg, h
    def place(self, over, drop=()):
        f, cfg, h = self.checkout_page()
        f.update(over)
        for k in drop: f.pop(k, None)
        raw = self.post("/?wc-ajax=checkout", f)
        try: j = json.loads(raw)
        except Exception: return {"result": "raw", "raw": raw[:300]}
        msgs = re.findall(r"<li[^>]*>(.*?)</li>", j.get("messages", ""), re.S)
        msgs = [re.sub(r"<[^>]+>", "", x).strip() for x in msgs]
        out = {"result": j.get("result"), "msgs": msgs}
        if j.get("redirect"):
            m = re.search(r"order-received/(\d+)", j["redirect"])
            out["order"] = int(m.group(1)) if m else j["redirect"]
        return out

BASEF = {"billing_first_name": "Prueba", "billing_last_name": "Automatizada", "billing_phone": "55550000", "payment_method": "cod", "billing_country": "GT", "billing_state": "GT-GU"}
