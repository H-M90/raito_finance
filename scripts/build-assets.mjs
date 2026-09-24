import { copyFile, mkdir } from 'node:fs/promises';
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

console.log('Static assets and Select2 Bootstrap 5 vendor assets synchronized.');
