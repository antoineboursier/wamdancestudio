#!/usr/bin/env node
// Extraction Search Console + Google Analytics 4, en lecture seule.
//
// Chaque lancement écrit un instantané DATÉ et jamais modifié ensuite dans
// .seo-archive/runs/<AAAAMMJJ-HHMMSS>/ (non versionné), avec un manifeste
// (fenêtre de dates, nombre de lignes, empreinte SHA-256 de chaque fichier).
// C'est la traçabilité : on peut toujours dire quelles données on avait,
// et quand, au moment d'un audit.
//
// Usage (depuis la racine du projet, Node 18+, aucune dépendance) :
//   node .ddev/scripts/seo/seo-pull.mjs check
//   node .ddev/scripts/seo/seo-pull.mjs pull                 (28 derniers jours)
//   node .ddev/scripts/seo/seo-pull.mjs pull --days 90
//   node .ddev/scripts/seo/seo-pull.mjs pull --backfill      (16 mois, max GSC)
//   node .ddev/scripts/seo/seo-pull.mjs pull --from 2026-07-01 --to 2026-10-31
//   node .ddev/scripts/seo/seo-pull.mjs summary              (dernier instantané)
//
// Configuration : .seo-archive/config.json (modèle : config.sample.json) et la
// clé du compte de service .seo-archive/google-key.json. Rien de tout cela
// n'est versionné (le .gitignore racine ignore tout sauf une liste blanche).

import { readFileSync, writeFileSync, mkdirSync, existsSync, appendFileSync, readdirSync } from 'node:fs';
import { createSign, createHash } from 'node:crypto';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const VERSION = 1;
const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', '..');
const ARCHIVE = process.env.SEO_ARCHIVE_DIR || join(ROOT, '.seo-archive');

// Les adresses sont surchargeables pour les essais contre une fausse API.
const GSC_BASE = process.env.SEO_GSC_BASE || 'https://www.googleapis.com/webmasters/v3';
const GA_BASE = process.env.SEO_GA_BASE || 'https://analyticsdata.googleapis.com/v1beta';
const TOKEN_URL_OVERRIDE = process.env.SEO_TOKEN_URL || '';

const SCOPES = [
  'https://www.googleapis.com/auth/webmasters.readonly',
  'https://www.googleapis.com/auth/analytics.readonly',
].join(' ');

// ---------------------------------------------------------------- utilitaires

const DAY = 86400000;
const iso = (d) => d.toISOString().slice(0, 10);
const daysAgo = (n) => new Date(Date.now() - n * DAY);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function fail(msg) {
  console.error('Erreur : ' + msg);
  process.exit(1);
}

function readJson(path, what) {
  if (!existsSync(path)) fail(`${what} introuvable : ${path}`);
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (e) {
    fail(`${what} illisible (${e.message})`);
  }
}

function loadConfig() {
  const cfg = readJson(join(ARCHIVE, 'config.json'), 'Configuration');
  for (const k of ['gsc_site', 'ga4_property']) {
    if (!cfg[k]) fail(`config.json : champ "${k}" manquant (voir config.sample.json)`);
  }
  const keyPath = resolve(ARCHIVE, cfg.key_file || 'google-key.json');
  const key = readJson(keyPath, 'Clé du compte de service');
  if (!key.client_email || !key.private_key) fail('La clé du compte de service est incomplète.');
  return { cfg, key };
}

// ----------------------------------------------------------------------- auth

async function getToken(key) {
  const tokenUrl = TOKEN_URL_OVERRIDE || key.token_uri || 'https://oauth2.googleapis.com/token';
  const now = Math.floor(Date.now() / 1000);
  const b64 = (o) => Buffer.from(JSON.stringify(o)).toString('base64url');
  const unsigned =
    b64({ alg: 'RS256', typ: 'JWT' }) +
    '.' +
    b64({ iss: key.client_email, scope: SCOPES, aud: tokenUrl, iat: now, exp: now + 3600 });
  const signature = createSign('RSA-SHA256').update(unsigned).sign(key.private_key).toString('base64url');

  const res = await fetch(tokenUrl, {
    method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      assertion: unsigned + '.' + signature,
    }),
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok || !body.access_token) {
    throw new Error(`Jeton refusé (${res.status}) : ${body.error_description || body.error || 'réponse inattendue'}`);
  }
  return body.access_token;
}

// Appel d'API avec 3 essais sur 429 et 5xx. Le jeton n'est jamais journalisé.
async function api(token, method, url, payload) {
  let last;
  for (let attempt = 1; attempt <= 3; attempt++) {
    const res = await fetch(url, {
      method,
      headers: { authorization: 'Bearer ' + token, 'content-type': 'application/json' },
      body: payload ? JSON.stringify(payload) : undefined,
    });
    if (res.ok) return res.json();
    const text = (await res.text()).slice(0, 400);
    last = new Error(`HTTP ${res.status} : ${text}`);
    if (res.status !== 429 && res.status < 500) break;
    await sleep(1000 * attempt);
  }
  throw last;
}

// ------------------------------------------------------------ Search Console

const GSC_REPORTS = [
  { name: 'gsc_par_jour', dimensions: ['date'] },
  { name: 'gsc_page_jour', dimensions: ['date', 'page'] },
  { name: 'gsc_requete_jour', dimensions: ['date', 'query'] },
  { name: 'gsc_requete_page', dimensions: ['query', 'page'] },
  { name: 'gsc_pays', dimensions: ['country'] },
  { name: 'gsc_appareil', dimensions: ['device'] },
  { name: 'gsc_apparence', dimensions: ['searchAppearance'] },
];

async function gscReport(token, site, from, to, dimensions) {
  const url = `${GSC_BASE}/sites/${encodeURIComponent(site)}/searchAnalytics/query`;
  const rowLimit = 25000;
  const out = [];
  for (let startRow = 0; ; startRow += rowLimit) {
    const data = await api(token, 'POST', url, {
      startDate: from,
      endDate: to,
      dimensions,
      rowLimit,
      startRow,
      dataState: 'final',
    });
    const rows = data.rows || [];
    for (const r of rows) {
      const row = {};
      dimensions.forEach((d, i) => (row[d] = r.keys[i]));
      row.clicks = r.clicks;
      row.impressions = r.impressions;
      row.ctr = r.ctr;
      row.position = r.position;
      out.push(row);
    }
    if (rows.length < rowLimit) break;
  }
  return out;
}

// ------------------------------------------------------------------------ GA4

const GA_REPORTS = [
  {
    name: 'ga4_par_jour',
    dimensions: ['date'],
    metrics: ['sessions', 'totalUsers', 'engagedSessions', 'engagementRate', 'averageSessionDuration', 'screenPageViews', 'keyEvents'],
  },
  {
    name: 'ga4_canaux_jour',
    dimensions: ['date', 'sessionDefaultChannelGroup'],
    metrics: ['sessions', 'engagedSessions', 'engagementRate', 'keyEvents'],
  },
  {
    name: 'ga4_source_support',
    dimensions: ['sessionSourceMedium'],
    metrics: ['sessions', 'engagedSessions', 'engagementRate', 'keyEvents'],
  },
  {
    name: 'ga4_source_support_jour',
    dimensions: ['date', 'sessionSourceMedium'],
    metrics: ['sessions', 'engagedSessions'],
  },
  {
    name: 'ga4_pages_entree',
    dimensions: ['landingPage', 'sessionDefaultChannelGroup'],
    metrics: ['sessions', 'engagedSessions', 'engagementRate', 'keyEvents'],
  },
  {
    name: 'ga4_pages',
    dimensions: ['pagePath'],
    metrics: ['screenPageViews', 'totalUsers', 'userEngagementDuration'],
  },
  {
    name: 'ga4_evenements',
    dimensions: ['eventName'],
    metrics: ['eventCount', 'totalUsers'],
  },
];

async function gaReport(token, property, from, to, def) {
  const url = `${GA_BASE}/properties/${property}:runReport`;
  const limit = 100000;
  const out = [];
  for (let offset = 0; ; offset += limit) {
    const data = await api(token, 'POST', url, {
      dateRanges: [{ startDate: from, endDate: to }],
      dimensions: def.dimensions.map((name) => ({ name })),
      metrics: def.metrics.map((name) => ({ name })),
      limit,
      offset,
    });
    const rows = data.rows || [];
    for (const r of rows) {
      const row = {};
      def.dimensions.forEach((d, i) => (row[d] = r.dimensionValues[i].value));
      def.metrics.forEach((m, i) => (row[m] = Number(r.metricValues[i].value)));
      out.push(row);
    }
    if (rows.length < limit) break;
  }
  return out;
}

// ------------------------------------------------------------------- commandes

function parseArgs(argv) {
  const a = { _: [] };
  for (let i = 0; i < argv.length; i++) {
    const v = argv[i];
    if (v === '--backfill') a.backfill = true;
    else if (['--days', '--from', '--to'].includes(v)) a[v.slice(2)] = argv[++i];
    else a._.push(v);
  }
  return a;
}

function windows(args) {
  // Search Console a 2 à 3 jours de retard ; GA4 : jusqu'à hier (aujourd'hui est partiel).
  const gscTo = args.to || iso(daysAgo(3));
  const gaTo = args.to || iso(daysAgo(1));
  let from;
  if (args.from) from = args.from;
  else if (args.backfill) {
    const d = new Date();
    d.setUTCMonth(d.getUTCMonth() - 16);
    from = iso(new Date(d.getTime() + DAY)); // marge : la limite GSC est de 16 mois
  } else from = iso(daysAgo(3 + (Number(args.days) || 28) - 1));
  for (const d of [from, gscTo, gaTo]) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) fail(`Date invalide : ${d} (format AAAA-MM-JJ)`);
  }
  return { gsc: { from, to: gscTo }, ga4: { from, to: gaTo } };
}

async function cmdCheck() {
  const { cfg, key } = loadConfig();
  console.log('Compte de service : ' + key.client_email);
  const token = await getToken(key);
  console.log('Authentification  : OK');

  const sites = await api(token, 'GET', `${GSC_BASE}/sites`);
  const list = sites.siteEntry || [];
  const mine = list.find((s) => s.siteUrl === cfg.gsc_site);
  console.log(
    'Search Console    : ' +
      (mine
        ? `OK (${cfg.gsc_site}, droit ${mine.permissionLevel})`
        : `ECHEC, "${cfg.gsc_site}" absent. Propriétés visibles : ${list.map((s) => s.siteUrl).join(', ') || 'aucune'}`)
  );

  try {
    const rows = await gaReport(token, cfg.ga4_property, iso(daysAgo(7)), iso(daysAgo(1)), {
      dimensions: ['date'],
      metrics: ['sessions'],
    });
    const total = rows.reduce((s, r) => s + r.sessions, 0);
    console.log(`GA4               : OK (propriété ${cfg.ga4_property}, ${total} sessions sur 7 jours)`);
  } catch (e) {
    console.log('GA4               : ECHEC, ' + e.message);
    process.exitCode = 1;
  }
  if (!mine) process.exitCode = 1;
}

async function cmdPull(args) {
  const { cfg, key } = loadConfig();
  const win = windows(args);
  const startedAt = new Date();
  const runId = startedAt.toISOString().replace(/[-:]/g, '').replace('T', '-').slice(0, 15);
  const dir = join(ARCHIVE, 'runs', runId);
  mkdirSync(dir, { recursive: true });

  console.log(`Instantané ${runId}`);
  console.log(`  Search Console : ${win.gsc.from} au ${win.gsc.to}`);
  console.log(`  GA4            : ${win.ga4.from} au ${win.ga4.to}`);

  const token = await getToken(key);
  const files = [];
  const errors = [];

  const save = (name, rows, meta) => {
    const content = JSON.stringify({ report: name, ...meta, rows }, null, 1);
    writeFileSync(join(dir, name + '.json'), content);
    files.push({
      name: name + '.json',
      rows: rows.length,
      bytes: Buffer.byteLength(content),
      sha256: createHash('sha256').update(content).digest('hex'),
    });
    console.log(`  ${name.padEnd(26)} ${String(rows.length).padStart(7)} lignes`);
  };

  for (const def of GSC_REPORTS) {
    try {
      const rows = await gscReport(token, cfg.gsc_site, win.gsc.from, win.gsc.to, def.dimensions);
      save(def.name, rows, { source: 'search-console', site: cfg.gsc_site, ...win.gsc, dimensions: def.dimensions });
    } catch (e) {
      errors.push({ source: 'search-console', report: def.name, message: e.message });
      console.log(`  ${def.name.padEnd(26)} ECHEC : ${e.message}`);
    }
  }
  for (const def of GA_REPORTS) {
    try {
      const rows = await gaReport(token, cfg.ga4_property, win.ga4.from, win.ga4.to, def);
      save(def.name, rows, { source: 'ga4', property: cfg.ga4_property, ...win.ga4, dimensions: def.dimensions, metrics: def.metrics });
    } catch (e) {
      errors.push({ source: 'ga4', report: def.name, message: e.message });
      console.log(`  ${def.name.padEnd(26)} ECHEC : ${e.message}`);
    }
  }

  const manifest = {
    tool: 'seo-pull',
    version: VERSION,
    run: runId,
    startedAt: startedAt.toISOString(),
    finishedAt: new Date().toISOString(),
    serviceAccount: key.client_email,
    gscSite: cfg.gsc_site,
    ga4Property: cfg.ga4_property,
    window: win,
    options: { days: args.days || null, from: args.from || null, to: args.to || null, backfill: !!args.backfill },
    files,
    errors,
  };
  writeFileSync(join(dir, 'manifest.json'), JSON.stringify(manifest, null, 1));
  appendFileSync(
    join(ARCHIVE, 'runs.jsonl'),
    JSON.stringify({ run: runId, at: manifest.finishedAt, window: win, files: files.length, rows: files.reduce((s, f) => s + f.rows, 0), errors: errors.length }) + '\n'
  );
  writeFileSync(join(ARCHIVE, 'latest.json'), JSON.stringify({ run: runId }));

  console.log(`\n${files.length} rapports archivés, ${errors.length} échec(s). Dossier : ${dir}`);
  summarize(dir);
  if (errors.length) process.exitCode = 1;
}

function readReport(dir, name) {
  const p = join(dir, name + '.json');
  return existsSync(p) ? JSON.parse(readFileSync(p, 'utf8')).rows : null;
}

function summarize(dir) {
  const sum = (rows, k) => rows.reduce((s, r) => s + (r[k] || 0), 0);
  const gsc = readReport(dir, 'gsc_par_jour');
  if (gsc && gsc.length) {
    const clicks = sum(gsc, 'clicks');
    const imp = sum(gsc, 'impressions');
    const pos = gsc.reduce((s, r) => s + r.position * r.impressions, 0) / (imp || 1);
    console.log(`\nSearch Console : ${clicks} clics, ${imp} impressions, CTR ${((clicks / (imp || 1)) * 100).toFixed(2)} %, position moyenne ${pos.toFixed(1)}`);
  }
  const ga = readReport(dir, 'ga4_par_jour');
  if (ga && ga.length) {
    console.log(`GA4            : ${sum(ga, 'sessions')} sessions, ${sum(ga, 'totalUsers')} utilisateurs (somme des jours), ${sum(ga, 'keyEvents')} évènements clés`);
  }
  const canaux = readReport(dir, 'ga4_canaux_jour');
  if (canaux && canaux.length) {
    const by = {};
    for (const r of canaux) by[r.sessionDefaultChannelGroup] = (by[r.sessionDefaultChannelGroup] || 0) + r.sessions;
    const top = Object.entries(by).sort((a, b) => b[1] - a[1]);
    console.log('Canaux GA4     : ' + top.map(([k, v]) => `${k} ${v}`).join(' | '));
  }
}

function cmdSummary() {
  const latest = join(ARCHIVE, 'latest.json');
  if (!existsSync(latest)) fail('Aucun instantané. Lancer d\'abord : pull');
  const { run } = JSON.parse(readFileSync(latest, 'utf8'));
  const dir = join(ARCHIVE, 'runs', run);
  const m = JSON.parse(readFileSync(join(dir, 'manifest.json'), 'utf8'));
  console.log(`Instantané ${run} (${m.window.gsc.from} au ${m.window.gsc.to}), ${m.files.length} rapports, ${m.errors.length} échec(s)`);
  summarize(dir);
  console.log('\nInstantanés disponibles : ' + readdirSync(join(ARCHIVE, 'runs')).length);
}

// ------------------------------------------------------------------------ main

const args = parseArgs(process.argv.slice(2));
const cmd = args._[0];
try {
  if (cmd === 'check') await cmdCheck();
  else if (cmd === 'pull') await cmdPull(args);
  else if (cmd === 'summary') cmdSummary();
  else {
    console.log('Usage : seo-pull.mjs check | pull [--days N | --backfill | --from D --to D] | summary');
    process.exitCode = cmd ? 1 : 0;
  }
} catch (e) {
  fail(e.message);
}
