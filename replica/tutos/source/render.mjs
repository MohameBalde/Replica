// node render.mjs <scenes.json> <dossier des captures> <sortie.mp4> [fps] [aperçu: secondes séparées par des virgules]
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
import { readFileSync, mkdirSync, rmSync } from 'fs';
import { execFileSync } from 'child_process';
import { dirname } from 'path';
const [scenesFile, shotDir, outFile, fpsArg, preview] = process.argv.slice(2);
const fps = Number(fpsArg || 30);
const scenes = JSON.parse(readFileSync(scenesFile, 'utf8'));
const boxes = JSON.parse(readFileSync(shotDir + '/boxes.json', 'utf8'));
for (const s of scenes) if (s.type === 'shot' && s.box === undefined) s.box = boxes[s.img];
for (const s of scenes) if (s.type === 'terminal') s.cmdLen = s.lines.join('\n').length;
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1080, height: 1920 } });
await page.goto('file://' + dirname(new URL(import.meta.url).pathname) + '/engine.html');
await page.evaluate(([sc, d]) => window.setup(sc, d), [scenes, shotDir]);
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(500);
const total = await page.evaluate(() => window.duration());
if (preview) {
  for (const t of preview.split(',').map(Number)) {
    await page.evaluate(x => window.render(x), t);
    await page.screenshot({ path: `${outFile}-${t}.png` });
  }
  console.log('aperçus faits, durée', total.toFixed(1), 's');
} else {
  const frames = outFile + '.frames'; rmSync(frames, { recursive: true, force: true }); mkdirSync(frames);
  const n = Math.ceil(total * fps);
  for (let i = 0; i < n; i++) {
    await page.evaluate(x => window.render(x), i / fps);
    await page.screenshot({ path: `${frames}/${String(i).padStart(5, '0')}.jpg`, type: 'jpeg', quality: 92 });
  }
  execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-framerate', String(fps), '-i', `${frames}/%05d.jpg`,
    '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-crf', '21', '-preset', 'medium', '-movflags', '+faststart', outFile]);
  rmSync(frames, { recursive: true, force: true });
  console.log('vidéo', outFile, total.toFixed(1), 's,', n, 'images');
}
await browser.close();
