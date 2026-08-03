const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const rootDir = path.resolve(__dirname, '..');
const pluginSlug = path.basename(rootDir);
const distDir = path.join(rootDir, 'dist');
const outputFile = path.join(distDir, `${pluginSlug}.zip`);

const excludedDirs = new Set(['.git', 'node_modules', 'src', 'dist']);
const excludedFiles = new Set([
  'AGENTS.md',
  'DESIGN.md',
  'composer.json',
  'package-lock.json',
  'package.json',
]);

function createCrc32Table() {
  const table = new Uint32Array(256);

  for (let i = 0; i < 256; i += 1) {
    let crc = i;

    for (let j = 0; j < 8; j += 1) {
      crc = crc & 1 ? 0xedb88320 ^ (crc >>> 1) : crc >>> 1;
    }

    table[i] = crc >>> 0;
  }

  return table;
}

const crc32Table = createCrc32Table();

function crc32(buffer) {
  let crc = 0xffffffff;

  for (const byte of buffer) {
    crc = crc32Table[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  }

  return (crc ^ 0xffffffff) >>> 0;
}

function getDosTimestamp(date) {
  const year = Math.max(date.getFullYear(), 1980);
  const dosTime =
    (date.getHours() << 11) |
    (date.getMinutes() << 5) |
    Math.floor(date.getSeconds() / 2);
  const dosDate =
    ((year - 1980) << 9) |
    ((date.getMonth() + 1) << 5) |
    date.getDate();

  return { dosTime, dosDate };
}

function shouldSkip(relativePath, dirent) {
  const baseName = dirent.name;

  if (dirent.isDirectory()) {
    return excludedDirs.has(baseName);
  }

  return excludedFiles.has(baseName) || relativePath.endsWith('.zip');
}

function collectFiles(currentDir = rootDir, currentRelative = '') {
  const files = [];
  const dirents = fs.readdirSync(currentDir, { withFileTypes: true });

  for (const dirent of dirents) {
    const relativePath = path.join(currentRelative, dirent.name);
    const absolutePath = path.join(currentDir, dirent.name);

    if (shouldSkip(relativePath, dirent)) {
      continue;
    }

    if (dirent.isDirectory()) {
      files.push(...collectFiles(absolutePath, relativePath));
      continue;
    }

    if (dirent.isFile()) {
      files.push({
        absolutePath,
        archivePath: path.posix.join(
          pluginSlug,
          relativePath.split(path.sep).join(path.posix.sep),
        ),
      });
    }
  }

  return files.sort((a, b) => a.archivePath.localeCompare(b.archivePath));
}

function uint16(value) {
  const buffer = Buffer.alloc(2);
  buffer.writeUInt16LE(value);
  return buffer;
}

function uint32(value) {
  const buffer = Buffer.alloc(4);
  buffer.writeUInt32LE(value >>> 0);
  return buffer;
}

function createZip(files) {
  const localParts = [];
  const centralParts = [];
  let offset = 0;

  for (const file of files) {
    const source = fs.readFileSync(file.absolutePath);
    const compressed = zlib.deflateRawSync(source, { level: 9 });
    const stats = fs.statSync(file.absolutePath);
    const { dosTime, dosDate } = getDosTimestamp(stats.mtime);
    const name = Buffer.from(file.archivePath, 'utf8');
    const checksum = crc32(source);

    const localHeader = Buffer.concat([
      uint32(0x04034b50),
      uint16(20),
      uint16(0),
      uint16(8),
      uint16(dosTime),
      uint16(dosDate),
      uint32(checksum),
      uint32(compressed.length),
      uint32(source.length),
      uint16(name.length),
      uint16(0),
      name,
    ]);

    const centralHeader = Buffer.concat([
      uint32(0x02014b50),
      uint16(20),
      uint16(20),
      uint16(0),
      uint16(8),
      uint16(dosTime),
      uint16(dosDate),
      uint32(checksum),
      uint32(compressed.length),
      uint32(source.length),
      uint16(name.length),
      uint16(0),
      uint16(0),
      uint16(0),
      uint16(0),
      uint32(0),
      uint32(offset),
      name,
    ]);

    localParts.push(localHeader, compressed);
    centralParts.push(centralHeader);
    offset += localHeader.length + compressed.length;
  }

  const centralDirectory = Buffer.concat(centralParts);
  const endOfCentralDirectory = Buffer.concat([
    uint32(0x06054b50),
    uint16(0),
    uint16(0),
    uint16(files.length),
    uint16(files.length),
    uint32(centralDirectory.length),
    uint32(offset),
    uint16(0),
  ]);

  return Buffer.concat([...localParts, centralDirectory, endOfCentralDirectory]);
}

fs.mkdirSync(distDir, { recursive: true });

const files = collectFiles();

if (files.length === 0) {
  throw new Error('No plugin files found to package.');
}

fs.writeFileSync(outputFile, createZip(files));

console.log(`Created ${path.relative(rootDir, outputFile)} with ${files.length} files.`);
