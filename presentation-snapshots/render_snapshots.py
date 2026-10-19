"""Render source excerpts and captured evidence; run from any directory."""
import json
import math
import textwrap
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
from pygments import lex
from pygments.lexers import get_lexer_for_filename
from pygments.styles import get_style_by_name

BASE = Path(__file__).resolve().parent
ROOT = BASE.parent
WIDTH = 1920
BG, PANEL, FG, MUTED, ACCENT = '#0b1220', '#121e30', '#e7eef7', '#a9bbd1', '#56d8c0'
FONT = ImageFont.truetype('C:/Windows/Fonts/consola.ttf', 26)
UI = ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 28)
TITLE = ImageFont.truetype('C:/Windows/Fonts/segoeui.ttf', 44)
STYLE = get_style_by_name('monokai')


def canvas(title, subtitle, height=1080):
    im = Image.new('RGB', (WIDTH, height), BG)
    d = ImageDraw.Draw(im)
    d.text((56, 32), title, font=TITLE, fill=FG)
    d.text((58, 96), subtitle, font=UI, fill=MUTED)
    d.line((56, 146, WIDTH - 56, 146), fill=ACCENT, width=3)
    d.text((58, height - 56), 'FHIR PATIENT DEMO  /  SYNTHETIC TEST DATA ONLY', font=UI, fill=MUTED)
    return im, d


def segments(line, limit=105):
    return [line[i:i + limit] for i in range(0, len(line), limit)] or ['']


def render_code(folder, spec):
    rows, excerpts = [], []
    for section in spec['sections']:
        path = section['path']
        source = (ROOT / path).read_text(encoding='utf-8').splitlines()
        start, end = section['start'], section['end']
        assert 1 <= start <= end <= len(source), (path, start, end)
        rows.append(('heading', f'{path}  |  lines {start}-{end}', None))
        excerpts.append(f'--- {path} : {start}-{end} ---')
        for n in range(start, end + 1):
            line = source[n - 1].expandtabs(4)
            excerpts.append(f'{n:4}  {line}')
            for i, chunk in enumerate(segments(line)):
                rows.append(('code', chunk, str(n) if i == 0 else '>'))
        rows.append(('space', '', None))
    height = max(1080, 250 + sum(48 if r[0] == 'heading' else 20 if r[0] == 'space' else 34 for r in rows))
    im, d = canvas(spec['title'], spec['caption'], height)
    y = 170
    for kind, line, number in rows:
        if kind == 'heading':
            d.rounded_rectangle((50, y - 3, WIDTH - 50, y + 39), 7, fill=PANEL)
            d.text((66, y), line, font=UI, fill=ACCENT)
            y += 48
        elif kind == 'space':
            y += 20
        else:
            d.text((65, y), number.rjust(4), font=FONT, fill=MUTED)
            x = 162
            lexer = get_lexer_for_filename(path, startinline=True)
            for token, value in lex(line, lexer):
                value = value.rstrip('\n')
                color = STYLE.style_for_token(token)['color']
                d.text((x, y), value, font=FONT, fill=f'#{color}' if color else FG)
                x += d.textlength(value, font=FONT)
            y += 34
    im.save(folder / 'code.png')
    (folder / 'code-excerpts.txt').write_text('\n'.join(excerpts) + '\n', encoding='utf-8')


def render_evidence(folder, spec):
    evidence = folder / 'evidence.txt'
    if not evidence.exists():
        return
    rows = [chunk for line in evidence.read_text(encoding='utf-8').splitlines() for chunk in segments(line)]
    im, d = canvas(spec['title'], 'Observed runtime/test output', max(1080, 250 + len(rows) * 34))
    for i, line in enumerate(rows):
        d.text((60, 174 + i * 34), line, font=FONT, fill=FG)
    im.save(folder / 'output-evidence.png')


def architecture():
    im, d = canvas('01 / System architecture', 'Implemented request, data, and FHIR exchange flow')
    def box(rect, title, body):
        d.rounded_rectangle(rect, 18, fill=PANEL, outline='#35516b', width=2)
        x, y, _, _ = rect
        d.text((x + 22, y + 18), title, font=UI, fill=ACCENT)
        for i, line in enumerate(body):
            d.text((x + 22, y + 66 + i * 37), line, font=UI, fill=FG)
    def arrow(points, label=None, label_xy=None):
        d.line(points, fill=ACCENT, width=4)
        x, y = points[-1]
        a = math.atan2(y - points[-2][1], x - points[-2][0])
        d.polygon([(x, y), (x - 16 * math.cos(a - .5), y - 16 * math.sin(a - .5)),
                   (x - 16 * math.cos(a + .5), y - 16 * math.sin(a + .5))], fill=ACCENT)
        if label:
            d.text(label_xy, label, font=UI, fill=MUTED)
    box((55, 210, 440, 415), 'Authenticated UI', ['Blade + Livewire', 'Registry / patient details'])
    box((540, 210, 950, 415), 'Laravel application', ['Auth + input validation', 'Patient model / service'])
    box((1070, 210, 1490, 415), 'MySQL persistence', ['Encrypted patient fields', 'via Eloquent casts'])
    box((540, 545, 950, 750), 'FHIR mapper', ['FhirPatientService', 'FHIR R4 Patient JSON'])
    box((1070, 545, 1490, 750), 'Preview / download', ['JSON preview in UI', 'Download JSON file'])
    box((55, 545, 440, 750), 'Audit logs', ['Attempt + outcome rows', 'Append-only safeguards'])
    box((540, 845, 1490, 975), 'HAPI FHIR endpoint (configurable)', ['HTTPS POST /Patient  |  HTTP status + remote ID response'])
    arrow([(440, 310), (540, 310)])
    arrow([(950, 310), (1070, 310)])
    arrow([(745, 415), (745, 545)])
    arrow([(950, 645), (1070, 645)])
    arrow([(540, 645), (440, 645)])
    arrow([(700, 750), (700, 845)], 'Send', (590, 784))
    arrow([(845, 845), (845, 750)], 'Response', (864, 785))
    d.text((58, 440), 'Source: routes/web.php; Patient.php; Show.php; FhirPatientService.php; AuditLog.php', font=UI, fill=MUTED)
    im.save(BASE / '01-system-architecture' / 'architecture.png')


if __name__ == '__main__':
    for manifest in sorted(BASE.glob('*/code.json')):
        data = json.loads(manifest.read_text(encoding='utf-8-sig'))
        render_code(manifest.parent, data)
        render_evidence(manifest.parent, data)
        print(f'Rendered {manifest.parent.name}')
    if (BASE / '01-system-architecture').exists():
        architecture()
