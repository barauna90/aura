"""
Extrai, dos PDFs OFICIAIS do Inep já baixados, o gabarito (texto do PDF de
gabarito) e o mapa "questão → página" (texto do caderno), gerando um manifesto
JSON consumido por `php artisan enem:import`.

Nada é inventado: todas as letras vêm do PDF de gabarito; a área de cada questão
segue a estrutura oficial das edições de dois dias (1–45 Linguagens, 46–90
Humanas, 91–135 Natureza, 136–180 Matemática; 1–5 = língua estrangeira).

Uso: python tools/inep_extract.py storage/inep storage/inep/manifest.json
"""
import json
import re
import sys
from pathlib import Path

from pypdf import PdfReader

SRC = {
    # ano: (base_pv, base_gb) — URLs oficiais (download.inep.gov.br)
    2019: ("https://download.inep.gov.br/educacao_basica/enem/provas/2019/2019_PV_impresso_D{d}_CD{c}.pdf",
           {1: "https://download.inep.gov.br/educacao_basica/enem/gabaritos/2019/gabarito_1_dia_caderno_1_azul_aplicacao_regular.pdf",
            2: "https://download.inep.gov.br/educacao_basica/enem/gabaritos/2019/gabarito_2_dia_caderno_5_amarelo_aplicacao_regular.pdf"}),
}
STD = "https://download.inep.gov.br/enem/provas_e_gabaritos/{y}_{k}_impresso_D{d}_CD{c}.pdf"
LINE = re.compile(r"^\s*(\d{1,3})\s+([A-E]|anulad[ao]|\*)(?:\s+([A-E]|anulad[ao]|\*))?\s*$", re.I)
FOOTNOTE = re.compile(r"quest[ãa]o\s+(\d{1,3})\s+anulad[ao]", re.I)
BARE = re.compile(r"^\s*(\d{1,3})\s*$")


def area_of(n: int) -> str:
    if n <= 45:
        return "LINGUAGENS"
    if n <= 90:
        return "HUMANAS"
    if n <= 135:
        return "NATUREZA"
    return "MATEMATICA"


def norm(v: str):
    v = v.strip()
    if v.upper().startswith("ANULAD") or v == "*":
        return None, True
    return v.upper(), False


def parse_gabarito(path: Path, day: int) -> list[dict]:
    text = "\n".join((p.extract_text() or "") for p in PdfReader(path).pages)
    answers: dict[int, dict] = {}
    lang_rows = 0
    # Anulações indicadas em nota de rodapé ("* Questão 114 Anulada") ou em linha só com o número.
    footnote_annulled = {int(n) for n in FOOTNOTE.findall(text)}
    bare_numbers = {int(m.group(1)) for m in (BARE.match(l) for l in text.splitlines()) if m}
    for raw in text.splitlines():
        m = LINE.match(raw.replace(" ", " "))
        if not m:
            continue
        n = int(m.group(1))
        first, second = m.group(2), m.group(3)
        if day == 1 and n <= 5 and second:
            # questões 1–5: colunas INGLÊS | ESPANHOL
            en, en_x = norm(first)
            es, es_x = norm(second)
            answers[(n, "INGLES")] = {"number": n, "area": "LINGUAGENS", "correct": en, "annulled": en_x, "foreign_language": "INGLES"}
            answers[(n, "ESPANHOL")] = {"number": n, "area": "LINGUAGENS", "correct": es, "annulled": es_x, "foreign_language": "ESPANHOL"}
            lang_rows += 1
            continue
        if second:  # linha inesperada com duas letras fora do bloco de idiomas
            continue
        c, x = norm(first)
        answers[(n, None)] = {"number": n, "area": area_of(n), "correct": c, "annulled": x, "foreign_language": None}
    for n in footnote_annulled:
        if (n, None) not in answers:
            answers[(n, None)] = {"number": n, "area": area_of(n), "correct": None, "annulled": True, "foreign_language": None}
    unresolved = {n for n in bare_numbers if (n, None) not in answers and 1 <= n <= 180 and n not in (1, 2)}
    if unresolved:
        raise SystemExit(f"{path.name}: questões sem gabarito legível no PDF: {sorted(unresolved)} — verifique manualmente")
    out = sorted(answers.values(), key=lambda a: (a["number"], a["foreign_language"] or ""))
    return out


def page_map(path: Path) -> tuple[int, dict[int, int]]:
    reader = PdfReader(path)
    pages: dict[int, int] = {}
    pat = re.compile(r"QUEST[ÃA]O\s+(\d{1,3})\b", re.I)
    for i, p in enumerate(reader.pages, start=1):
        t = p.extract_text() or ""
        for m in pat.finditer(t):
            n = int(m.group(1))
            if 1 <= n <= 180 and n not in pages:
                pages[n] = i
    return len(reader.pages), pages


def main(src: Path, out: Path):
    manifest = []
    for y in range(2019, 2025):
        for d in (1, 2):
            c = 1 if d == 1 else 5
            pv = src / f"{y}_PV_impresso_D{d}_CD{c}.pdf"
            gb = src / f"{y}_GB_impresso_D{d}_CD{c}.pdf"
            if not pv.exists() or not gb.exists():
                print(f"[skip] {y} D{d}: arquivos ausentes", file=sys.stderr)
                continue
            answers = parse_gabarito(gb, d)
            expected = 95 if d == 1 else 90  # dia 1: 90 + 5 extras de idioma
            if len(answers) != expected:
                print(f"[warn] {y} D{d}: {len(answers)} respostas (esperado {expected})", file=sys.stderr)
            n_pages, pages = page_map(pv)
            for a in answers:
                a["page"] = pages.get(a["number"])
            if y in SRC:
                pv_url = SRC[y][0].format(d=d, c=c)
                gb_url = SRC[y][1][d]
            else:
                pv_url = STD.format(y=y, k="PV", d=d, c=c)
                gb_url = STD.format(y=y, k="GB", d=d, c=c)
            manifest.append({
                "year": y, "day": d, "application": "REGULAR",
                "title": f"ENEM {y} — {d}º dia" + (" (Linguagens, Humanas e Redação)" if d == 1 else " (Natureza e Matemática)"),
                "duration_minutes": 330 if d == 1 else 300,
                "areas": ["LINGUAGENS", "HUMANAS", "REDACAO"] if d == 1 else ["NATUREZA", "MATEMATICA"],
                "structure_note": "Duração conforme o edital da edição: 5h30 no 1º dia e 5h no 2º dia. Caderno impresso da aplicação regular; cadernos de outras cores têm a mesma prova em ordem diferente.",
                "booklet": {"color": "AZUL" if d == 1 else "AMARELO", "label": f"Caderno {c} — {'Azul' if d == 1 else 'Amarelo'}", "file": pv.name, "page_count": n_pages, "source_url": pv_url},
                "answer_key": {"file": gb.name, "source_url": gb_url, "answers": answers},
                "stats": {"answers": len(answers), "annulled": sum(1 for a in answers if a["annulled"]), "pages_mapped": sum(1 for a in answers if a["page"])},
            })
            print(f"[ok] {y} D{d}: {len(answers)} respostas, {n_pages} páginas, {manifest[-1]['stats']['pages_mapped']} com página, anuladas={manifest[-1]['stats']['annulled']}")
    out.write_text(json.dumps(manifest, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"manifesto: {out} ({len(manifest)} provas)")


if __name__ == "__main__":
    main(Path(sys.argv[1]), Path(sys.argv[2]))
