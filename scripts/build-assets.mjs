import { copyFile, mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import { constants } from 'node:fs';

await mkdir('public/assets/vendor', { recursive: true });

const copies = [
    ['resources/js/app.js', 'public/assets/app.js'],
    ['resources/css/app.css', 'public/assets/app.css'],
    ['node_modules/jquery/dist/jquery.min.js', 'public/assets/vendor/jquery.min.js'],
    ['node_modules/select2/dist/js/select2.full.min.js', 'public/assets/vendor/select2.min.js'],
    ['node_modules/select2/dist/css/select2.min.css', 'public/assets/vendor/select2.min.css'],
    ['node_modules/select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.rtl.min.css', 'public/assets/vendor/select2-bootstrap-5-theme.rtl.min.css'],
];

for (const [source, target] of copies) {
    await copyFile(source, target, constants.COPYFILE_FICLONE);
}

const bladeFiles = async directory => (await Promise.all((await readdir(directory, { withFileTypes: true })).map(async entry => {
    const path = `${directory}/${entry.name}`;
    return entry.isDirectory() ? bladeFiles(path) : (entry.name.endsWith('.blade.php') ? [path] : []);
}))).flat();
const names = new Set();
for (const file of await bladeFiles('resources/views')) {
    const template = await readFile(file, 'utf8');
    for (const match of template.matchAll(/<x-ui-icon\s+name="([a-z0-9-]+)"/g)) names.add(match[1]);
}
const symbols = [];
for (const name of [...names].sort()) {
    const svg = await readFile(`node_modules/lucide-static/icons/${name}.svg`, 'utf8');
    const content = svg.match(/<svg\b[^>]*>([\s\S]*?)<\/svg>/)?.[1];
    if (!content) throw new Error(`Invalid Lucide icon: ${name}`);
    symbols.push(`<symbol id="${name}" viewBox="0 0 24 24">${content.trim()}</symbol>`);
}
await writeFile('public/assets/lucide-icons.svg', `<svg xmlns="http://www.w3.org/2000/svg"><defs>${symbols.join('')}</defs></svg>`);

console.log(`Static assets synchronized with ${symbols.length} Lucide icons.`);
