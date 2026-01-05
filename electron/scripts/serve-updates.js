const http = require('http');
const path = require('path');
const fs = require('fs');

const dirArg = process.argv[2] || 'dist';
const portArg = process.argv[3] || '5050';

const baseDir = path.resolve(process.cwd(), dirArg);
const port = Number(portArg);

if (!Number.isFinite(port) || port <= 0) {
  console.error('Porta inválida. Ex.: node scripts/serve-updates.js dist 5050');
  process.exit(1);
}

function contentType(filePath) {
  const ext = path.extname(filePath).toLowerCase();
  if (ext === '.yml' || ext === '.yaml') return 'text/yaml; charset=utf-8';
  if (ext === '.exe') return 'application/octet-stream';
  if (ext === '.blockmap') return 'application/octet-stream';
  if (ext === '.json') return 'application/json; charset=utf-8';
  if (ext === '.txt' || ext === '.log') return 'text/plain; charset=utf-8';
  return 'application/octet-stream';
}

function safeJoin(requestPath) {
  const decoded = decodeURIComponent(requestPath);
  const clean = decoded.replace(/^\/+/, '');
  const full = path.resolve(baseDir, clean);
  if (!full.startsWith(baseDir)) return null;
  return full;
}

const server = http.createServer((req, res) => {
  if (!req.url) {
    res.writeHead(400);
    res.end('Bad Request');
    return;
  }

  const requestPath = req.url.split('?')[0];
  const filePath = safeJoin(requestPath === '/' ? '/latest.yml' : requestPath);

  if (!filePath) {
    res.writeHead(403);
    res.end('Forbidden');
    return;
  }

  fs.stat(filePath, (err, stat) => {
    if (err || !stat.isFile()) {
      res.writeHead(404);
      res.end('Not Found');
      return;
    }

    res.writeHead(200, {
      'Content-Type': contentType(filePath),
      'Cache-Control': 'no-cache',
    });

    fs.createReadStream(filePath).pipe(res);
  });
});

server.listen(port, '127.0.0.1', () => {
  console.log(`Servindo updates em http://127.0.0.1:${port}`);
  console.log(`Diretório: ${baseDir}`);
  console.log('Arquivos esperados: latest.yml, *.exe, *.blockmap');
});
