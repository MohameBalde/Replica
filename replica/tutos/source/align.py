"""Repères exacts de chaque paragraphe dans la voix, d'après une transcription mot à mot.

python3 align.py <voix-off.md> <nom de section> <mots.json> <sortie.json>
mots.json : [{"w": mot, "s": début, "e": fin}, ...] (transcription Whisper).
"""
import difflib
import json
import re
import sys
import unicodedata


def norm(w: str) -> str:
    w = unicodedata.normalize('NFD', w.lower())
    return re.sub(r'[^a-z0-9]', '', ''.join(c for c in w if unicodedata.category(c) != 'Mn'))


def paragraphs(md: str, section: str) -> list[str]:
    block = re.search(r'## ' + re.escape(section) + r'.*?```\n(.*?)```', md, re.S).group(1)
    return [p.strip() for p in re.split(r'<break[^>]*/>', block) if p.strip()]


def main(md_file, section, words_file, out):
    paras = paragraphs(open(md_file).read(), section)
    words = [w for w in json.load(open(words_file)) if norm(w['w'])]
    script, owner = [], []
    for i, p in enumerate(paras):
        for w in p.split():
            if norm(w):
                script.append(norm(w)); owner.append(i)
    heard = [norm(w['w']) for w in words]
    # Pour chaque mot entendu, le paragraphe du mot du texte auquel il correspond.
    para_of = [None] * len(heard)
    for tag, a1, a2, b1, b2 in difflib.SequenceMatcher(None, script, heard, autojunk=False).get_opcodes():
        if tag in ('equal', 'replace'):
            for k in range(b2 - b1):
                para_of[b1 + k] = owner[min(a1 + k, a2 - 1)] if a2 > a1 else None
    # Mots non appariés : même paragraphe que le mot précédent (ou suivant au tout début).
    last = 0
    for k in range(len(para_of)):
        if para_of[k] is None:
            para_of[k] = last
        last = para_of[k]
    segs = []
    for i in range(len(paras)):
        idx = [k for k, p in enumerate(para_of) if p == i]
        if not idx:
            sys.exit(f'Paragraphe {i + 1} introuvable dans la voix : « {paras[i][:40]} »')
        segs.append([max(0.0, words[idx[0]]['s'] - 0.08), words[idx[-1]]['e'] + 0.12])
    for i in range(len(segs) - 1):  # jamais de chevauchement
        segs[i][1] = min(segs[i][1], segs[i + 1][0])
    json.dump(segs, open(out, 'w'))
    for i, (a, b) in enumerate(segs):
        print(f'{i + 1:2d}  {a:6.2f} → {b:6.2f}  {paras[i][:60]}')


if __name__ == '__main__':
    main(*sys.argv[1:5])
