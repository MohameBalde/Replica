"""Montage voix-off : cale chaque scène d'un tuto sur sa phrase.

python3 voiceover.py <scenes.json> <voix.mp3|wav> <captures> <sortie.mp4>

1. Découpe la voix aux silences (un paragraphe = une scène). S'il y a plus de
   morceaux que de scènes (pause au milieu d'une phrase), les plus courts
   silences sont recollés ; s'il y en a moins, on s'arrête avec un message.
2. Chaque scène dure le temps de sa phrase + une petite marge (2,8 s minimum).
3. Rendu de la vidéo avec ces durées (render.mjs), puis mixage de la voix,
   volume normalisé, en AAC.
"""
import json
import re
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
LEAD = 0.45       # la voix démarre 0,45 s après le début de la scène (le texte apparaît d'abord)
TAIL = 0.65       # marge après la phrase
MIN_SCENE = 2.8
SILENCE_DB = -38
SILENCE_MIN = 0.45


def speech_segments(audio: str) -> tuple[list[tuple[float, float]], float]:
    out = subprocess.run(['ffmpeg', '-hide_banner', '-i', audio, '-af', f'silencedetect=noise={SILENCE_DB}dB:d={SILENCE_MIN}', '-f', 'null', '-'],
                         capture_output=True, text=True).stderr
    total = float(re.search(r'Duration: (\d+):(\d+):([\d.]+)', out).expand(r'\1 \2 \3').split()[2]) \
        + 60 * float(re.search(r'Duration: (\d+):(\d+)', out).group(2)) + 3600 * float(re.search(r'Duration: (\d+)', out).group(1))
    starts = [float(x) for x in re.findall(r'silence_start: ([\d.]+)', out)]
    ends = [float(x) for x in re.findall(r'silence_end: ([\d.]+)', out)]
    silences = list(zip(starts, ends + [total] * (len(starts) - len(ends))))
    segs, cur = [], 0.0
    for s, e in silences:
        if s - cur > 0.15:
            segs.append((cur, s))
        cur = e
    if total - cur > 0.15:
        segs.append((cur, total))
    return segs, total


def fit(segs: list[tuple[float, float]], n: int) -> list[tuple[float, float]]:
    segs = list(segs)
    while len(segs) > n:  # recolle les deux morceaux séparés par le plus court silence
        gaps = [segs[i + 1][0] - segs[i][1] for i in range(len(segs) - 1)]
        i = gaps.index(min(gaps))
        segs[i:i + 2] = [(segs[i][0], segs[i + 1][1])]
    if len(segs) < n:
        sys.exit(f'La voix a {len(segs)} phrases séparées par une pause, il en faut {n} (une par scène). '
                 'Vérifiez la ligne vide / la pause entre chaque paragraphe.')
    return segs


def main(scenes_file: str, audio: str, shots: str, out: str) -> None:
    scenes = json.loads(Path(scenes_file).read_text())
    segs = fit(speech_segments(audio)[0], len(scenes))
    starts, t = [], 0.0
    for scene, (a, b) in zip(scenes, segs):
        scene['dur'] = round(max(MIN_SCENE, LEAD + (b - a) + TAIL), 2)
        starts.append(t)
        t += scene['dur']
    scenes[-1]['dur'] += 1.0
    timed = Path(out).with_suffix('.scenes.json')
    timed.write_text(json.dumps(scenes, ensure_ascii=False, indent=1))

    silent = str(Path(out).with_suffix('.muet.mp4'))
    subprocess.run(['node', str(HERE / 'render.mjs'), str(timed), shots, silent, '30'], check=True)

    # Piste voix : chaque phrase posée au début de sa scène (+ LEAD), volume normalisé.
    parts = ''.join(f'[0:a]atrim={a:.3f}:{b:.3f},asetpts=PTS-STARTPTS,adelay={int((s + LEAD) * 1000)}:all=1[a{i}];'
                    for i, ((a, b), s) in enumerate(zip(segs, starts)))
    mix = ''.join(f'[a{i}]' for i in range(len(segs))) + f'amix=inputs={len(segs)}:normalize=0,loudnorm=I=-16:TP=-1.5:LRA=11,apad[voix]'
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', audio, '-i', silent, '-filter_complex', parts + mix,
                    '-map', '1:v', '-map', '[voix]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '128k', '-ar', '44100',
                    '-shortest', '-movflags', '+faststart', out], check=True)
    Path(silent).unlink()
    print(f'{out} : {sum(s["dur"] for s in scenes):.1f} s, {len(scenes)} scènes calées sur la voix')


if __name__ == '__main__':
    main(*sys.argv[1:5])
