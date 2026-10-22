import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';
import { writeFileSync } from 'fs';
const OUT = process.argv[2], B = 'http://127.0.0.1:8130';
const browser = await chromium.launch();
const scenes = {};
const ctxOpts = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 };
const mk = async (state) => (await browser.newContext(state ? { ...ctxOpts, storageState: state } : ctxOpts)).newPage();
async function snap(p, id, target, opts = {}) {
  await p.evaluate(() => document.querySelectorAll('.toast').forEach(t => t.remove()));
  let box = null;
  if (target) {
    const loc = typeof target === 'string' ? p.locator(target).first() : target;
    await loc.evaluate((el, block) => el.scrollIntoView({ block }), opts.block || 'center');
    await p.waitForTimeout(150);
    box = await loc.boundingBox();
  } else { await p.evaluate(() => scrollTo(0, 0)); }
  await p.waitForTimeout(200);
  await p.screenshot({ path: `${OUT}/${id}.png` });
  scenes[id] = box ? { x: box.x / 390, y: box.y / 844, w: box.width / 390, h: box.height / 844 } : null;
  console.log('ok', id, JSON.stringify(scenes[id]));
}
const login = async (p, phone, pass) => { await p.goto(B + '/connexion'); await p.fill('#phone', phone); await p.fill('#password', pass); await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]); };

// Commerçant : inscription (formulaire rempli, pas envoyé) puis création de boutique
let p = await mk();
await p.goto(B + '/inscription');
await p.fill('#name', 'Aïssatou Camara'); await p.fill('#phone', '622 00 00 01'); await p.fill('#password', 'motdepasse'); await p.fill('#password_confirmation', 'motdepasse');
await snap(p, 'm01', 'button[type=submit]');
await p.close();
p = await mk(); await login(p, '622 00 00 01', 'motdepasse');
await p.goto(B + '/admin/boutique/creer');
await p.fill('#name', 'Aïssa Mode'); await p.fill('#slug', 'aissa-mode'); await p.fill('#whatsapp_number', '622 00 00 01');
await snap(p, 'm02', '#slug');
await p.close();

p = await mk(); await login(p, '620 00 00 00', 'password');
const merchant = await p.context().storageState();
await p.goto(B + '/admin/produits/creer');
await p.fill('#name', 'Maillot Syli National 2026'); await p.fill('#price', '250 000');
await snap(p, 'm03', '#price');
await p.evaluate(() => document.querySelectorAll('details').forEach(d => d.open = true));
await p.fill('#v0-name', 'M'); await p.fill('#v0-stock', '5');
await p.fill('#a0-name', 'Flocage'); await p.fill('#a0-price', '50 000'); await p.fill('#a0-text', 'Nom et numéro au dos');
await snap(p, 'm04', '#a0-name');
await p.goto(B + '/admin/paiement');
await snap(p, 'm05', p.locator('text=Numéro marchand').first());
await p.goto(B + '/admin/partager', { waitUntil: 'networkidle' });
await snap(p, 'm06', p.locator('button:has-text("Copier"), a:has-text("Copier")').first());
await snap(p, 'm07', p.locator('text=Télécharger l\'image').first());
await p.close();

// Client
p = await mk();
await p.goto(B + '/nimstore'); await snap(p, 'c01', p.locator('a[href*="/p/survetement-real-madrid-2026"]').first(), { block: 'center' });
await p.goto(B + '/nimstore/p/survetement-real-madrid-2026');
await snap(p, 'c02', 'text=Choisissez');
await p.click('input[value=floc0001]'); await p.fill('#addon-input-floc0001', 'DIALLO 10');
await snap(p, 'c03', '#addon-input-floc0001');
await snap(p, 'c04', 'button:has-text("Ajouter au panier")', { block: 'end' });
await Promise.all([p.waitForNavigation(), p.click('button:has-text("Ajouter au panier")')]);
await p.goto(B + '/nimstore/panier');
await snap(p, 'c05', 'a:has-text("Commander")', { block: 'end' });
await p.goto(B + '/nimstore/commande');
await p.fill('#name', 'Mamadou Diallo'); await p.fill('#phone', '621 00 00 07');
await snap(p, 'c06', '#phone');
await p.fill('#address', 'Kipé, rue KA-020');
const ans = p.locator('#answer-0'); if (await ans.count()) await ans.fill('Près de la pharmacie, portail noir');
await p.locator('label', { hasText: 'Orange Money' }).first().click();
await snap(p, 'c07', p.locator('label', { hasText: 'Orange Money' }).first());
await snap(p, 'c08', 'button:has-text("Valider ma commande")', { block: 'end' });
await Promise.all([p.waitForNavigation(), p.click('button:has-text("Valider ma commande")')]);
await snap(p, 'c09', 'a:has-text("Envoyer ma commande sur WhatsApp")');
await snap(p, 'c10', '#reference');
await p.close();

// Commerçant : la commande arrive
p = await mk(merchant);
await p.goto(B + '/admin', { waitUntil: 'networkidle' });
await p.evaluate(() => { const b = document.querySelector('[data-install]'); if (b) b.hidden = false; });
await snap(p, 'm08', '[data-install]', { block: 'start' });
await p.evaluate(() => scrollTo(0, 0));
await snap(p, 'm09', p.locator('text=À faire maintenant').first(), { block: 'start' });
await p.goto(B + '/admin/commandes');
await snap(p, 'm10', p.locator('text=WhatsApp non envoyé').first());
const first = await p.locator('a[href]').evaluateAll(as => as.map(a => a.getAttribute('href')).find(h => /\/admin\/commandes\/\d+$/.test(h)));
await p.goto(first.startsWith('http') ? first : B + first);
await snap(p, 'm11', p.locator('text=Confirmer la commande').first());
await snap(p, 'm12', p.locator('button:has-text("Enregistrer")').first());

// Équipe
await p.goto(B + '/gestion');
await snap(p, 't01', null);
await snap(p, 't02', p.locator('text=Dernières actions').first(), { block: 'start' });
const s = await p.locator('a[href]').evaluateAll(as => as.map(a => a.getAttribute('href')).find(h => /\/gestion\/boutiques\/\d+$/.test(h)));
await p.goto(s.startsWith('http') ? s : B + s);
await snap(p, 't03', p.locator('text=Écrire sur WhatsApp').first());
await snap(p, 't04', p.locator('button:has-text("Enregistrer le paiement")').first());
await snap(p, 't05', p.locator('button:has-text("Suspendre")').first());
writeFileSync(`${OUT}/boxes.json`, JSON.stringify(scenes, null, 1));
await browser.close();
