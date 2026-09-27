#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
اعتبارسنج سخت‌گیرانه XLSX — همان چیزی که اکسل اهمیت می‌دهد ولی openpyxl چک نمی‌کند:
  1) سلامت ZIP
  2) پوشش کامل [Content_Types].xml (Override برای همه شیت‌ها، بدون Override برای بخش ناموجود)
  3) خوش‌فرمی XML همه بخش‌ها
  4) سازگاری workbook.xml ↔ workbook.xml.rels (r:id و Target)
  5) یکتایی numFmtId در styles.xml
  6) ترتیب عناصر worksheet (printOptions قبل از pageMargins/pageSetup)
  7) بازشدن با openpyxl + خواندن چند سلول
"""
import sys, zipfile, re
import xml.etree.ElementTree as ET

NS_CT = 'http://schemas.openxmlformats.org/package/2006/content-types'
NS_R  = 'http://schemas.openxmlformats.org/package/2006/relationships'
NS_WB = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
NS_WR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'

def fail(msg, errors):
    errors.append(msg)

def validate(path):
    errors = []
    z = zipfile.ZipFile(path)
    bad = z.testzip()
    if bad: fail(f'zip corrupt: {bad}', errors)
    names = set(z.namelist())

    # ---------- 2) Content Types ----------
    ct = ET.fromstring(z.read('[Content_Types].xml'))
    overrides = {}
    for ov in ct.findall(f'{{{NS_CT}}}Override'):
        pn = ov.get('PartName'); overrides[pn] = ov.get('ContentType')
    defaults = {d.get('Extension'): d.get('ContentType') for d in ct.findall(f'{{{NS_CT}}}Default')}

    # شیت‌ها از workbook.xml
    wb = ET.fromstring(z.read('xl/workbook.xml'))
    sheets = wb.findall(f'{{{NS_WB}}}sheets/{{{NS_WB}}}sheet')
    n_sheets = len(sheets)
    for i in range(1, n_sheets + 1):
        pn = f'/xl/worksheets/sheet{i}.xml'
        if pn.lstrip('/') not in names: fail(f'missing part {pn}', errors)
        if pn not in overrides:
            fail(f'NO Override for {pn} (Excel: corrupt!)', errors)
        elif overrides[pn] != 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml':
            fail(f'wrong ContentType for {pn}: {overrides[pn]}', errors)
    # Override برای بخش ناموجود ممنوع
    for pn in overrides:
        part = pn.lstrip('/')
        if part not in names:
            fail(f'Override for non-existent part {pn} (Excel: corrupt!)', errors)
    # پوشش بخش‌های xml/rels بدون Default
    for n in names:
        ext = n.rsplit('.', 1)[-1].lower()
        if ext in defaults: continue
        if '/' + n not in overrides and n != '[Content_Types].xml':
            fail(f'part {n} not covered by Default/Override', errors)

    # ---------- 3) خوش‌فرمی همه XML ----------
    for n in names:
        if n.endswith('.xml') or n.endswith('.rels'):
            try: ET.fromstring(z.read(n))
            except ET.ParseError as e: fail(f'XML not well-formed {n}: {e}', errors)

    # ---------- 4) rels سازگار ----------
    rels = ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))
    relmap = {r.get('Id'): r.get('Target') for r in rels.findall(f'{{{NS_R}}}Relationship')}
    for sh in sheets:
        rid = sh.get(f'{{{NS_WR}}}id')
        if rid not in relmap:
            fail(f'sheet "{sh.get("name")}" r:id {rid} missing in rels', errors)
        else:
            tgt = 'xl/' + relmap[rid].lstrip('/')
            if tgt not in names: fail(f'rels target missing: {tgt}', errors)
    if not any('styles.xml' in t for t in relmap.values()):
        fail('styles.xml relationship missing', errors)

    # ---------- 5) numFmt یکتا ----------
    st = ET.fromstring(z.read('xl/styles.xml'))
    seen_ids = set()
    for nf in st.findall(f'{{{NS_WB}}}numFmts/{{{NS_WB}}}numFmt'):
        nid = nf.get('numFmtId')
        if nid in seen_ids: fail(f'DUPLICATE numFmtId {nid} (Excel: unreadable content)', errors)
        seen_ids.add(nid)
    for xf in st.findall(f'{{{NS_WB}}}cellXfs/{{{NS_WB}}}xf'):
        nid = int(xf.get('numFmtId', '0'))
        if nid >= 164 and str(nid) not in seen_ids:
            fail(f'cellXfs references unknown custom numFmtId {nid}', errors)

    # ---------- 5b) فونت‌ها: ترتیب عناصر + صفت الزامی val روی name ----------
    # ترتیب مرجع اکسل در CT_Font: b, i, strike, u, vertAlign, sz, color, name, family, charset, scheme
    # نقض ترتیب یا <name> بدون val → اکسل کل styles.xml را حذف می‌کند
    # («Removed Part: /xl/styles.xml … Load error» + Repaired Records سلول‌ها).
    FONT_ORDER = ['b', 'i', 'strike', 'condense', 'extend', 'outline', 'shadow', 'u', 'vertalign', 'sz', 'color', 'name', 'family', 'charset', 'scheme']
    fonts_el = st.find(f'{{{NS_WB}}}fonts')
    if fonts_el is None:
        fail('styles.xml has no <fonts>', errors)
    else:
        for fi, font in enumerate(fonts_el.findall(f'{{{NS_WB}}}font')):
            idxs = []
            tags = []
            for child in font:
                tag = child.tag.split('}')[1].lower()
                tags.append(tag)
                if tag in FONT_ORDER: idxs.append(FONT_ORDER.index(tag))
                else: fail(f'font[{fi}] unknown child <{tag}>', errors)
            if idxs != sorted(idxs):
                fail(f'font[{fi}] element order violates CT_Font: {tags} (Excel drops styles.xml!)', errors)
            name_el = font.find(f'{{{NS_WB}}}name')
            if name_el is not None and not name_el.get('val'):
                fail(f'font[{fi}] <name> missing required val attribute', errors)
            sz_el = font.find(f'{{{NS_WB}}}sz')
            if sz_el is not None and not sz_el.get('val'):
                fail(f'font[{fi}] <sz> missing val attribute', errors)
    # پرکردن‌های none/gray125 نباید fgColor داشته باشند (فرم مرجع اکسل)
    fills_el = st.find(f'{{{NS_WB}}}fills')
    if fills_el is not None:
        for fill in fills_el.findall(f'{{{NS_WB}}}fill')[:2]:
            pf = fill.find(f'{{{NS_WB}}}patternFill')
            if pf is not None and pf.get('patternType') in ('none', 'gray125') and len(list(pf)) > 0:
                fail(f'fill patternType={pf.get("patternType")} should have no children (Excel canonical form)', errors)

    # ---------- 6) ترتیب عناصر worksheet ----------
    order = ['sheetpr','dimensions','sheetviews','sheetformatpr','cols','sheetdata',
             'mergecells','printoptions','pagemargins','pagesetup']
    for i in range(1, n_sheets + 1):
        root = ET.fromstring(z.read(f'xl/worksheets/sheet{i}.xml'))
        idxs = []
        for child in root:
            tag = child.tag.split('}')[1].lower()
            if tag in order: idxs.append(order.index(tag))
        if idxs != sorted(idxs):
            fail(f'sheet{i}.xml element order violates schema: {[c.tag.split("}")[1] for c in root]}', errors)

    # ---------- 7) openpyxl ----------
    import openpyxl
    wb2 = openpyxl.load_workbook(path)
    snames = wb2.sheetnames
    if len(snames) != n_sheets: fail(f'sheet count mismatch {snames}', errors)
    ws = wb2[snames[0]]
    _ = ws.cell(1, 1).value

    return errors, n_sheets

if __name__ == '__main__':
    all_ok = True
    for path in sys.argv[1:]:
        try:
            errs, nsheets = validate(path)
        except Exception as e:
            print(f'[FAIL] {path}: {type(e).__name__}: {e}')
            all_ok = False
            continue
        if errs:
            all_ok = False
            print(f'[FAIL] {path} ({nsheets} sheets):')
            for e in errs: print('   -', e)
        else:
            print(f'[PASS] {path} ({nsheets} sheet(s))')
    sys.exit(0 if all_ok else 1)
