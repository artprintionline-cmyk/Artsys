const { app, BrowserWindow, dialog, nativeImage } = require('electron');
const path = require('path');
const fs = require('fs');
const { spawn, spawnSync } = require('child_process');
const net = require('net');
const waitOn = require('wait-on');
const AdmZip = require('adm-zip');
const log = require('electron-log');
const { autoUpdater } = require('electron-updater');

let mainWindow = null;
let backendProcess = null;
let startedBackend = false;

const BACKEND_URL = 'http://127.0.0.1:8000';
const BACKEND_HOST = '127.0.0.1';
const BACKEND_PORT = '8000';

const AUTO_UPDATE_ENABLED = app.isPackaged;

function exists(p) {
  try {
    fs.accessSync(p);
    return true;
  } catch {
    return false;
  }
}

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function rmDirRecursiveSafe(dir) {
  try {
    fs.rmSync(dir, { recursive: true, force: true });
  } catch {
    // noop
  }
}

function copyDirRecursiveSync(src, dest) {
  ensureDir(dest);

  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (const entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);

    if (entry.isDirectory()) {
      copyDirRecursiveSync(srcPath, destPath);
      continue;
    }

    if (entry.isSymbolicLink()) {
      // Evitar links simbólicos em build Windows.
      continue;
    }

    fs.copyFileSync(srcPath, destPath);
  }
}

function copyDirRecursiveWithExcludesSync(src, dest, shouldSkipRelativePath) {
  ensureDir(dest);

  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (const entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    const rel = entry.name;

    if (shouldSkipRelativePath(rel, srcPath, destPath)) {
      continue;
    }

    if (entry.isDirectory()) {
      copyDirRecursiveWithExcludesSync(srcPath, destPath, (childRel, childSrc, childDest) => {
        const combined = path.posix.join(rel.replace(/\\/g, '/'), childRel.replace(/\\/g, '/'));
        return shouldSkipRelativePath(combined, childSrc, childDest);
      });
      continue;
    }

    if (entry.isSymbolicLink()) {
      continue;
    }

    ensureDir(path.dirname(destPath));
    fs.copyFileSync(srcPath, destPath);
  }
}

function resolveBackendDir() {
  if (app.isPackaged) {
    // Em produção, rodar de um local gravável (Program Files é read-only).
    return path.join(app.getPath('userData'), 'erp-api');
  }

  // Dev: repo local
  return path.join(__dirname, '..', 'erp-api');
}

function resolveBundledBackendSourceDir() {
  // Mantido apenas para dev (repo local). Em produção usamos backend.zip.
  return path.join(process.resourcesPath, 'erp-api');
}

function resolveBundledBackendZip() {
  if (!app.isPackaged) {
    return null;
  }

  return path.join(process.resourcesPath, 'backend.zip');
}

function resolveBackendVersionMarker(backendDir) {
  return path.join(backendDir, '.desktop-app-version');
}

function readTextFileSafe(filePath) {
  try {
    return fs.readFileSync(filePath, 'utf8').trim();
  } catch {
    return null;
  }
}

function writeTextFileSafe(filePath, value) {
  try {
    fs.writeFileSync(filePath, value, 'utf8');
  } catch {
    // noop
  }
}

function resolveStartBackendBat() {
  if (app.isPackaged) {
    return path.join(process.resourcesPath, 'start-backend.bat');
  }

  return path.join(__dirname, '..', 'start-backend.bat');
}

function resolvePhpExe() {
  // Em produção (packaged): **sempre** usar PHP embutido.
  if (app.isPackaged) {
    return path.join(process.resourcesPath, 'php', 'php.exe');
  }

  // Dev: permitir override via ERP_PHP_PATH.
  const envPhp = process.env.ERP_PHP_PATH;
  if (envPhp && exists(envPhp)) return envPhp;

  // Dev: fallback para PATH.
  return 'php';
}

function resolveBundledPhpIniDir() {
  if (app.isPackaged) {
    return path.join(process.resourcesPath, 'php');
  }
  return path.join(__dirname, 'php');
}

function ensurePortFree(host, port) {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.unref();
    server.on('error', (err) => {
      if (err && err.code === 'EADDRINUSE') {
        return reject(
          new Error(
            [
              `A porta ${port} já está em uso (${host}).`,
              '',
              'Feche o processo que está usando a porta 8000 e tente novamente.',
              'Dica: no Windows, use: netstat -ano | findstr :8000',
            ].join('\n')
          )
        );
      }
      return reject(err);
    });
    server.listen(port, host, () => {
      server.close(() => resolve());
    });
  });
}

async function waitBackendReady(timeoutMs) {
  await waitOn({
    resources: [BACKEND_URL],
    timeout: timeoutMs,
    interval: 500,
    window: 1000,
    validateStatus: function (status) {
      return status >= 200 && status < 500; // 401/403 ok para considerar "vivo"
    },
  });
}

async function ensureBackendCopiedIfNeeded(backendDir) {
  if (!app.isPackaged) return;

  const marker = path.join(backendDir, 'artisan');

  // Se já existe, pode ser um upgrade (sincroniza se a versão do app mudou).
  if (exists(marker)) {
    await ensureBackendSyncedIfNeeded(backendDir);
    return;
  }

  const zipPath = resolveBundledBackendZip();
  if (!zipPath || !exists(zipPath)) {
    throw new Error('Backend não encontrado (resources/backend.zip).');
  }

  ensureDir(backendDir);
  const zip = new AdmZip(zipPath);
  zip.extractAllTo(backendDir, true);

  // Marca a versão do backend como a versão do app.
  writeTextFileSafe(resolveBackendVersionMarker(backendDir), app.getVersion());
}

async function ensureBackendSyncedIfNeeded(backendDir) {
  if (!app.isPackaged) return;

  const currentAppVersion = app.getVersion();
  const markerPath = resolveBackendVersionMarker(backendDir);
  const lastSyncedVersion = readTextFileSafe(markerPath);

  if (lastSyncedVersion === currentAppVersion) {
    return;
  }

  const zipPath = resolveBundledBackendZip();
  if (!zipPath || !exists(zipPath)) {
    // Sem zip embutido, não tem o que sincronizar.
    return;
  }

  const stagingDir = path.join(app.getPath('userData'), 'erp-api-staging');
  rmDirRecursiveSafe(stagingDir);
  ensureDir(stagingDir);

  const zip = new AdmZip(zipPath);
  zip.extractAllTo(stagingDir, true);

  // Copia por cima preservando dados locais.
  const shouldSkip = (relPath) => {
    const normalized = relPath.replace(/\\/g, '/');

    // Preservar configurações e dados locais:
    if (normalized === '.env') return true;
    if (normalized === 'database/database.sqlite') return true;

    // Preservar uploads/arquivos do usuário.
    if (normalized.startsWith('storage/app/')) return true;

    // Evitar sobrescrever logs/sessões/cache voláteis.
    if (normalized.startsWith('storage/logs/')) return true;
    if (normalized.startsWith('storage/framework/sessions/')) return true;
    if (normalized.startsWith('storage/framework/cache/')) return true;
    if (normalized.startsWith('storage/framework/views/')) return true;

    return false;
  };

  // Copia tudo do staging para o backendDir (com exclusões acima).
  const entries = fs.readdirSync(stagingDir, { withFileTypes: true });
  for (const entry of entries) {
    const srcPath = path.join(stagingDir, entry.name);
    const destPath = path.join(backendDir, entry.name);

    if (shouldSkip(entry.name)) {
      continue;
    }

    if (entry.isDirectory()) {
      copyDirRecursiveWithExcludesSync(srcPath, destPath, (rel) => shouldSkip(rel));
      continue;
    }

    if (entry.isSymbolicLink()) {
      continue;
    }

    fs.copyFileSync(srcPath, destPath);
  }

  rmDirRecursiveSafe(stagingDir);
  writeTextFileSafe(markerPath, currentAppVersion);
}

function runErpInstallIfNeeded(backendDir, phpExe) {
  const envPath = path.join(backendDir, '.env');
  const dbPath = path.join(backendDir, 'database', 'database.sqlite');

  // Se já tem .env e DB, assume instalado (comando também é idempotente).
  const args = ['artisan', 'erp:install', '--sqlite'];

  return new Promise((resolve, reject) => {
    const child = spawn(phpExe, args, {
      cwd: backendDir,
      windowsHide: true,
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    let out = '';
    let err = '';
    child.stdout?.on('data', (d) => (out += d.toString()));
    child.stderr?.on('data', (d) => (err += d.toString()));

    child.on('error', reject);
    child.on('exit', (code) => {
      // O comando agora retorna 0 mesmo quando já instalado.
      if (code === 0) return resolve();

      // fallback: se os arquivos essenciais existem, não bloqueia startup.
      if (exists(envPath) && exists(dbPath)) return resolve();

      const summary = [
        `erp:install falhou (exit ${code}).`,
        out ? `\nSTDOUT:\n${out.trim()}` : '',
        err ? `\nSTDERR:\n${err.trim()}` : '',
      ]
        .filter(Boolean)
        .join('\n');

      log.error('[backend] erp:install falhou', summary);
      return reject(new Error(summary));
    });
  });
}

function runMigrations(backendDir, phpExe) {
  return new Promise((resolve, reject) => {
    const child = spawn(phpExe, ['artisan', 'migrate', '--force'], {
      cwd: backendDir,
      windowsHide: true,
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    let out = '';
    let err = '';
    child.stdout?.on('data', (d) => (out += d.toString()));
    child.stderr?.on('data', (d) => (err += d.toString()));

    child.on('error', reject);
    child.on('exit', (code) => {
      if (code === 0) return resolve();

      const summary = [
        `migrate falhou (exit ${code}).`,
        out ? `\nSTDOUT:\n${out.trim()}` : '',
        err ? `\nSTDERR:\n${err.trim()}` : '',
      ]
        .filter(Boolean)
        .join('\n');

      log.error('[backend] migrate falhou', summary);
      return reject(new Error(summary));
    });
  });
}

function checkPhpHasSqliteExtensions(phpExe) {
  try {
    const res = spawnSync(phpExe, ['-m'], {
      windowsHide: true,
      encoding: 'utf8',
      env: {
        ...process.env,
        // Força carregar php.ini do bundle (importante em produção).
        PHPRC: resolveBundledPhpIniDir(),
      },
    });
    const output = `${res.stdout || ''}\n${res.stderr || ''}`.toLowerCase();
    const hasPdoSqlite = output.includes('pdo_sqlite');
    const hasSqlite3 = output.includes('sqlite3');
    return { ok: hasPdoSqlite || hasSqlite3, hasPdoSqlite, hasSqlite3, raw: output };
  } catch (e) {
    return { ok: false, hasPdoSqlite: false, hasSqlite3: false, raw: String(e) };
  }
}

function setupAutoUpdate() {
  // Logs em arquivo: %AppData%\<app>\logs\main.log
  log.transports.file.level = 'info';
  log.transports.console.level = 'warn';

  autoUpdater.logger = log;
  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;

  const isWindows = process.platform === 'win32';

  function overlaySvg(color) {
    // Ícone simples (seta circular) em SVG, renderizado via data URL.
    const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
  <path fill="${color}" d="M12 6V3L8 7l4 4V8c2.76 0 5 2.24 5 5a5 5 0 0 1-9.9 1H5.02A7.002 7.002 0 0 0 19 13c0-3.87-3.13-7-7-7z"/>
</svg>`;
    return nativeImage.createFromDataURL(`data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`);
  }

  function setUpdateIndicator({ state, percent }) {
    if (!mainWindow || mainWindow.isDestroyed()) return;

    // Barra de progresso na taskbar (Windows e macOS).
    if (state === 'downloading' && typeof percent === 'number') {
      mainWindow.setProgressBar(Math.max(0, Math.min(1, percent / 100)));
    } else if (state === 'checking') {
      // Indeterminado
      mainWindow.setProgressBar(2);
    } else if (state === 'ready') {
      mainWindow.setProgressBar(-1);
    } else if (state === 'clear') {
      mainWindow.setProgressBar(-1);
    }

    // Overlay icon na taskbar (apenas Windows).
    if (!isWindows) return;

    try {
      if (state === 'checking') {
        mainWindow.setOverlayIcon(overlaySvg('#3b82f6'), 'Verificando atualizações');
      } else if (state === 'downloading') {
        mainWindow.setOverlayIcon(overlaySvg('#f59e0b'), 'Baixando atualização');
      } else if (state === 'ready') {
        mainWindow.setOverlayIcon(overlaySvg('#22c55e'), 'Atualização pronta');
      } else if (state === 'clear') {
        mainWindow.setOverlayIcon(null, '');
      }
    } catch (e) {
      // Não quebrar o app por causa do overlay.
      log.warn('[update] falha ao aplicar overlay icon', e);
    }
  }

  // Permite testar update local sem depender de GitHub Releases.
  // Ex.: set ERP_UPDATE_URL=http://127.0.0.1:5050
  const updateUrl = process.env.ERP_UPDATE_URL;
  if (AUTO_UPDATE_ENABLED && updateUrl) {
    try {
      autoUpdater.setFeedURL({ provider: 'generic', url: updateUrl });
      log.info(`[update] feed override: ${updateUrl}`);
    } catch (e) {
      log.warn('[update] falha ao aplicar ERP_UPDATE_URL', e);
    }
  }

  const status = (msg) => {
    log.info(`[update] ${msg}`);
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.setTitle(`ERP — ${msg}`);
    }
  };

  autoUpdater.on('checking-for-update', () => status('Verificando atualizações...'));
  autoUpdater.on('checking-for-update', () => setUpdateIndicator({ state: 'checking' }));

  autoUpdater.on('update-available', () => {
    status('Atualização disponível. Baixando...');
    setUpdateIndicator({ state: 'downloading' });
  });

  autoUpdater.on('update-not-available', () => {
    status('Sem atualizações.');
    setUpdateIndicator({ state: 'clear' });
  });
  autoUpdater.on('download-progress', (p) => {
    const percent = p && typeof p.percent === 'number' ? Math.round(p.percent) : null;
    status(percent != null ? `Baixando atualização... ${percent}%` : 'Baixando atualização...');
    if (percent != null) {
      setUpdateIndicator({ state: 'downloading', percent });
    }
  });
  autoUpdater.on('error', (err) => {
    log.error('[update] erro', err);
    setUpdateIndicator({ state: 'clear' });
    // Não bloquear o app se updates não estiverem configurados.
  });
  autoUpdater.on('update-downloaded', async () => {
    status('Atualização pronta.');
    setUpdateIndicator({ state: 'ready' });
    try {
      const res = await dialog.showMessageBox({
        type: 'info',
        buttons: ['Reiniciar agora', 'Depois'],
        defaultId: 0,
        cancelId: 1,
        title: 'Atualização disponível',
        message: 'Uma atualização foi baixada e está pronta para instalar.',
        detail: 'Deseja reiniciar o ERP agora para aplicar a atualização?',
      });

      if (res.response === 0) {
        autoUpdater.quitAndInstall();
      }
    } catch (e) {
      log.error('[update] falha ao mostrar diálogo', e);
    }
  });

  if (!AUTO_UPDATE_ENABLED) {
    return;
  }

  // Se não houver publish configurado corretamente, electron-updater só vai logar erro.
  try {
    autoUpdater.checkForUpdates();
  } catch (e) {
    log.warn('[update] checkForUpdates falhou (provavelmente sem publish configurado)', e);
  }
}

async function startBackend() {
  // Se já estiver respondendo, não inicia outro processo.
  try {
    await waitBackendReady(1500);
    startedBackend = false;
    return;
  } catch {
    // continua
  }

  const backendDir = resolveBackendDir();
  await ensureBackendCopiedIfNeeded(backendDir);

  const bat = resolveStartBackendBat();
  if (!exists(bat)) {
    throw new Error('start-backend.bat não encontrado.');
  }

  const phpExe = resolvePhpExe();

  // Em produção, PHP precisa existir dentro do app.
  if (app.isPackaged && !exists(phpExe)) {
    throw new Error(
      [
        'PHP embutido não encontrado dentro do aplicativo.',
        '',
        `Esperado em: ${phpExe}`,
        '',
        'Reinstale usando o instalador mais recente (que inclui o PHP).',
      ].join('\n')
    );
  }

  // Se não achou php embutido e não está no PATH, vamos falhar com mensagem clara.
  if (phpExe !== 'php' && !exists(phpExe)) {
    throw new Error('PHP não encontrado.');
  }

  const sqliteCheck = checkPhpHasSqliteExtensions(phpExe);
  if (!sqliteCheck.ok) {
    log.error('[backend] PHP sem extensões SQLite', {
      phpExe,
      hasPdoSqlite: sqliteCheck.hasPdoSqlite,
      hasSqlite3: sqliteCheck.hasSqlite3,
    });

    if (app.isPackaged) {
      throw new Error(
        [
          'PHP embutido não possui extensões SQLite necessárias (pdo_sqlite/sqlite3).',
          '',
          'Reinstale usando o instalador mais recente (que inclui o PHP correto).',
        ].join('\n')
      );
    }

    throw new Error(
      [
        'PHP não possui extensões SQLite necessárias (pdo_sqlite/sqlite3).',
        '',
        'O ERP Desktop usa SQLite no modo local.',
        '',
        'Como resolver (dev):',
        '- Instale um PHP para Windows que inclua SQLite.',
        '- Ou configure a variável ERP_PHP_PATH apontando para um php.exe com SQLite habilitado.',
      ].join('\n')
    );
  }

  const childEnv = {
    ...process.env,
    ERP_BACKEND_DIR: backendDir,
    ERP_PHP: phpExe,
    ERP_BACKEND_HOST: BACKEND_HOST,
    ERP_BACKEND_PORT: BACKEND_PORT,
    // Garante que o php.exe carregue o php.ini do bundle.
    PHPRC: resolveBundledPhpIniDir(),
  };

  // Garante instalação local (SQLite, migrations, seed) sem exigir terminal.
  await runErpInstallIfNeeded(backendDir, phpExe);
  // Sempre roda migrations no start (atualizações não devem quebrar schema).
  await runMigrations(backendDir, phpExe);

  // Antes de subir o servidor, garanta que a porta esteja livre.
  await ensurePortFree(BACKEND_HOST, Number(BACKEND_PORT));

  backendProcess = spawn('cmd.exe', ['/c', bat], {
    windowsHide: true,
    env: childEnv,
    stdio: 'ignore',
  });

  startedBackend = true;

  backendProcess.on('exit', () => {
    backendProcess = null;
  });

  // Espera o backend ficar pronto; se o processo morrer antes disso, falha com erro claro.
  await Promise.race([
    waitBackendReady(60_000),
    new Promise((_, reject) => {
      backendProcess.once('exit', (code) => {
        reject(new Error(`Backend encerrou antes de ficar pronto (exit ${code}).`));
      });
    }),
  ]);
}

function killBackend() {
  if (!backendProcess || !startedBackend) return;

  try {
    if (process.platform === 'win32') {
      spawn('taskkill', ['/PID', String(backendProcess.pid), '/T', '/F'], { windowsHide: true, stdio: 'ignore' });
    } else {
      backendProcess.kill('SIGTERM');
    }
  } catch {
    // noop
  }
}

async function createMainWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    show: false,
    backgroundColor: '#ffffff',
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
    },
  });

  // Evita janela branca: só mostra quando estiver pronto.
  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
  });

  // Abrir direto no dashboard para evitar cair no /login quando o React Router
  // não tem rota explícita para '/'. O PrivateRoute vai redirecionar para /login
  // apenas quando não houver token.
  await mainWindow.loadURL(`${BACKEND_URL}/dashboard`);

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore();
      mainWindow.focus();
    }
  });

  app.on('before-quit', () => {
    killBackend();
  });

  app.whenReady().then(async () => {
    try {
      await startBackend();
      await createMainWindow();
      setupAutoUpdate();
    } catch (err) {
      const message = err && err.message ? err.message : String(err);

      dialog.showErrorBox(
        'Erro ao iniciar o ERP',
        [
          'Não foi possível iniciar o backend local.',
          '',
          'Detalhes:',
          message,
          '',
          'Dicas:',
          '- Confirme que a porta 8000 está livre.',
          '- Reinstale usando o instalador mais recente (com PHP embutido).',
        ].join('\n')
      );

      app.quit();
    }
  });

  app.on('window-all-closed', () => {
    // No Windows, encerra o app quando todas as janelas fecharem.
    app.quit();
  });
}
