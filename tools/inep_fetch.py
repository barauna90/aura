"""
Baixa, direto do site oficial do Inep (download.inep.gov.br), os cadernos (PV)
e gabaritos (GB) impressos da aplicação regular do ENEM 2019–2024:
1º dia = Caderno 1 (Azul), 2º dia = Caderno 5 (Amarelo).

Os PDFs não ficam no repositório (≈76 MB, documentos oficiais do Inep).
Depois de baixar, gere o manifesto e importe:

    python tools/inep_fetch.py storage/inep
    python tools/inep_extract.py storage/inep storage/inep/manifest.json
    php artisan enem:import --publish

Uso: python tools/inep_fetch.py [pasta_destino] [--years 2019-2024]
"""
import sys
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from inep_extract import SRC, STD  # noqa: E402 — mesmas URLs oficiais usadas no manifesto


def urls(years):
    for y in years:
        for d in (1, 2):
            c = 1 if d == 1 else 5
            if y in SRC:
                yield f"{y}_PV_impresso_D{d}_CD{c}.pdf", SRC[y][0].format(d=d, c=c)
                yield f"{y}_GB_impresso_D{d}_CD{c}.pdf", SRC[y][1][d]
            else:
                yield f"{y}_PV_impresso_D{d}_CD{c}.pdf", STD.format(y=y, k="PV", d=d, c=c)
                yield f"{y}_GB_impresso_D{d}_CD{c}.pdf", STD.format(y=y, k="GB", d=d, c=c)


def main(dest: Path, years):
    dest.mkdir(parents=True, exist_ok=True)
    for name, url in urls(years):
        target = dest / name
        if target.exists() and target.stat().st_size > 0:
            print(f"[skip] {name} já existe")
            continue
        print(f"[get ] {url}")
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (AuraSimulados importer)"})
        with urllib.request.urlopen(req, timeout=120) as r, open(target, "wb") as f:
            data = r.read()
            if not data.startswith(b"%PDF"):
                raise SystemExit(f"{name}: resposta não é um PDF (verifique a URL oficial)")
            f.write(data)
        print(f"[ok  ] {name} ({target.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    span = next((a.split("=", 1)[1] for a in sys.argv[1:] if a.startswith("--years=")), "2019-2024")
    lo, hi = (int(x) for x in span.split("-"))
    main(Path(args[0]) if args else Path("storage/inep"), range(lo, hi + 1))
