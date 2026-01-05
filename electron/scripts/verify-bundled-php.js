const fs = require('fs');
const path = require('path');

function exists(p) {
  try {
    fs.accessSync(p);
    return true;
  } catch {
    return false;
  }
}

function main() {
  const electronDir = path.join(__dirname, '..');
  const phpDir = path.join(electronDir, 'php');
  const phpExe = path.join(phpDir, 'php.exe');
  const phpIni = path.join(phpDir, 'php.ini');

  const missing = [];
  if (!exists(phpDir)) missing.push('php/');
  if (!exists(phpExe)) missing.push('php/php.exe');
  if (!exists(phpIni)) missing.push('php/php.ini');

  if (missing.length > 0) {
    console.error('[verify-bundled-php] PHP embutido ausente. Itens faltando:');
    for (const m of missing) console.error(`- ${m}`);
    console.error('\nComo resolver:');
    console.error('- Copie um runtime PHP para electron/php (veja electron/php/README.md)');
    console.error('- Ou rode: powershell -ExecutionPolicy Bypass -File scripts/fetch-php.ps1');
    process.exit(1);
  }

  console.log('[verify-bundled-php] OK: PHP embutido encontrado.');
}

main();
