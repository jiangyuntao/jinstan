import { copyFile, mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { transform as minifyJavaScript } from 'esbuild';
import { transform as minifyCss } from 'lightningcss';

const projectPath = resolve(import.meta.dirname, '..');
const versionSource = await readFile(resolve(projectPath, 'core/src/Version.php'), 'utf8');
const version = versionSource.match(/CURRENT\s*=\s*'([^']+)'/)?.[1];
if (!version || !/^[A-Za-z0-9][A-Za-z0-9._-]*$/.test(version)) {
  throw new Error('无法读取有效的 Core 版本。');
}

const outputPath = resolve(projectPath, 'public/assets/core', version);
await rm(outputPath, { recursive: true, force: true });
await mkdir(outputPath, { recursive: true });

const copy = async (source, target) => {
  const destination = resolve(outputPath, target);
  await mkdir(resolve(destination, '..'), { recursive: true });
  await copyFile(resolve(projectPath, source), destination);
};

const buildJavaScript = async (source, target) => {
  const input = await readFile(resolve(projectPath, source), 'utf8');
  const result = await minifyJavaScript(input, {
    legalComments: 'inline',
    minify: true,
    target: ['es2020'],
  });
  const destination = resolve(outputPath, target);
  await mkdir(resolve(destination, '..'), { recursive: true });
  await writeFile(destination, result.code);
};

const buildCss = async (source, target) => {
  const input = await readFile(resolve(projectPath, source));
  await buildCssCode(input, source, target);
};

const buildCssCode = async (input, filename, target) => {
  const result = compileCss(input, filename);
  const destination = resolve(outputPath, target);
  await mkdir(resolve(destination, '..'), { recursive: true });
  await writeFile(destination, result.code);
};

await buildCss('resources/assets/admin.css', 'admin.css');
await buildJavaScript('node_modules/alpinejs/dist/cdn.min.js', 'alpine.min.js');
await buildJavaScript('node_modules/chart.js/dist/chart.umd.js', 'chart.js/chart.umd.min.js');
await buildJavaScript('node_modules/codeflask/build/codeflask.min.js', 'codeflask.min.js');
await buildIconSubset();
await copy('node_modules/@tabler/icons-webfont/dist/fonts/tabler-icons.woff2', 'tabler-icons/fonts/tabler-icons.woff2');

await copy('node_modules/chart.js/LICENSE.md', 'licenses/chart.js-LICENSE.md');
await copy('node_modules/codeflask/LICENSE', 'licenses/codeflask-LICENSE');
await copy('node_modules/github-markdown-css/license', 'licenses/github-markdown-css-LICENSE');
await copy('node_modules/@tabler/icons-webfont/LICENSE', 'licenses/tabler-icons-LICENSE');
await copy('resources/assets/licenses/alpinejs-LICENSE', 'licenses/alpinejs-LICENSE');
const versions = Object.fromEntries(await Promise.all([
  'alpinejs',
  'chart.js',
  'codeflask',
  'github-markdown-css',
  '@tabler/icons-webfont',
].map(async (packageName) => [packageName, await readVersion(packageName)])));
await writeFile(resolve(outputPath, 'licenses/NOTICE.md'), `# Frontend dependencies\n\nThe release assets are built from npm packages pinned in package-lock.json.\n\n- Alpine.js ${versions.alpinejs} — MIT\n- Chart.js ${versions['chart.js']} — MIT\n- CodeFlask ${versions.codeflask} — MIT\n- GitHub Markdown CSS ${versions['github-markdown-css']} — MIT\n- Tabler Icons Webfont ${versions['@tabler/icons-webfont']} — MIT\n`);
await buildDefaultThemeStyles();

async function readVersion(packageName) {
  const metadata = JSON.parse(await readFile(resolve(projectPath, 'node_modules', packageName, 'package.json'), 'utf8'));
  return metadata.version;
}

function compileCss(code, filename) {
  return minifyCss({
    code,
    filename,
    minify: true,
    targets: { chrome: 80 << 16, firefox: 78 << 16, safari: 14 << 16 },
  });
}

async function buildDefaultThemeStyles() {
  const sourcePath = resolve(projectPath, 'resources/themes/default/styles.jst');
  const template = await readFile(sourcePath, 'utf8');
  const css = template.match(/^<style n:syntax="off">\s*([\s\S]*?)\s*<\/style>\s*$/)?.[1];
  if (css === undefined) {
    throw new Error('默认主题样式源码格式无效。');
  }
  const markdownCss = await readFile(resolve(projectPath, 'node_modules/github-markdown-css/github-markdown-light.css'));
  const result = compileCss(Buffer.concat([markdownCss, Buffer.from('\n'), Buffer.from(css)]), 'default-theme.css');
  await writeFile(
    resolve(projectPath, 'themes/default/partials/styles.jst'),
    `<style n:syntax="off">${result.code.toString()}</style>\n`,
  );
}

async function buildIconSubset() {
  const icons = new Set();
  for (const directory of ['core', 'site', 'plugins', 'themes']) {
    for (const file of await sourceFiles(resolve(projectPath, directory))) {
      const source = await readFile(file, 'utf8');
      for (const match of source.matchAll(/\bti-[a-z0-9-]+/g)) {
        icons.add(match[0]);
      }
    }
  }

  const source = await readFile(resolve(projectPath, 'node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css'), 'utf8');
  const rules = [];
  for (const icon of [...icons].sort()) {
    const match = source.match(new RegExp(`\\.${icon}:before\\{content:"([^"]+)"\\}`));
    if (!match) {
      throw new Error(`Tabler Icons 资源中缺少 ${icon}。`);
    }
    rules.push(`.${icon}:before{content:"${match[1]}"}`);
  }
  const iconVersion = await readVersion('@tabler/icons-webfont');
  const css = `@font-face{font-family:tabler-icons;font-style:normal;font-weight:400;font-display:block;src:url("./fonts/tabler-icons.woff2?v=${iconVersion}") format("woff2")}.ti{font-family:tabler-icons!important;speak:none;font-style:normal;font-weight:400;font-variant:normal;text-transform:none;line-height:1;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}${rules.join('')}`;
  await buildCssCode(Buffer.from(css), 'tabler-icons.css', 'tabler-icons/tabler-icons.min.css');
}

async function sourceFiles(directory) {
  const files = [];
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = resolve(directory, entry.name);
    if (entry.isDirectory()) {
      if (entry.name !== 'vendor') files.push(...await sourceFiles(path));
    } else if (/\.(?:php|jst|js)$/.test(entry.name)) {
      files.push(path);
    }
  }
  return files;
}

process.stdout.write(`前端资源已构建：public/assets/core/${version}\n`);
