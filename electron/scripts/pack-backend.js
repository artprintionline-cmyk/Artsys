const path = require('path');
const fs = require('fs');
const { spawnSync } = require('child_process');

function main() {
  const repoRoot = path.join(__dirname, '..', '..');
  const backendDir = path.join(repoRoot, 'erp-api');
  const outDir = path.join(__dirname, '..', 'build');
  const outZip = path.join(outDir, 'backend.zip');

  const shouldSkip = process.env.SKIP_BACKEND_ZIP === '1' || process.env.SKIP_BACKEND_ZIP === 'true';

  if (!fs.existsSync(path.join(backendDir, 'artisan'))) {
    console.error('Não encontrei o backend em ../erp-api (faltando artisan).');
    process.exit(1);
  }

  fs.mkdirSync(outDir, { recursive: true });

  if (shouldSkip) {
    if (fs.existsSync(outZip)) {
      const size = fs.statSync(outZip).size;
      if (size > 0) {
        console.log(`SKIP_BACKEND_ZIP ativo: reutilizando backend.zip existente (${Math.round(size / 1024 / 1024)} MB)`);
        return;
      }

      console.error('SKIP_BACKEND_ZIP ativo, mas build/backend.zip está vazio/corrompido. Rode o build uma vez sem SKIP_BACKEND_ZIP.');
      process.exit(1);
    }

    console.error('SKIP_BACKEND_ZIP ativo, mas build/backend.zip não existe. Rode o build uma vez sem SKIP_BACKEND_ZIP.');
    process.exit(1);
  }

  if (fs.existsSync(outZip)) fs.unlinkSync(outZip);

  const sevenZip = path.join(__dirname, '..', 'node_modules', '7zip-bin', 'win', 'x64', '7za.exe');
  if (!fs.existsSync(sevenZip)) {
    console.error('7za.exe não encontrado (node_modules/7zip-bin). Rode npm install.');
    process.exit(1);
  }

  const args = [
    'a',
    '-tzip',
    '-bd',
    '-mx=5',
    outZip,
    '.',
    '-xr!.git',
    '-xr!node_modules',
    '-xr!storage\\logs',
    '-xr!storage\\framework\\cache',
    '-xr!storage\\framework\\sessions',
    '-xr!storage\\framework\\views',
    '-x!database\\database.sqlite',
    '-x!.env',
  ];

  const res = spawnSync(sevenZip, args, {
    cwd: backendDir,
    stdio: 'inherit',
  });

  if (res.status !== 0) {
    process.exit(res.status ?? 1);
  }

  const size = fs.statSync(outZip).size;
  console.log(`backend.zip gerado: ${outZip} (${Math.round(size / 1024 / 1024)} MB)`);
}

main();
