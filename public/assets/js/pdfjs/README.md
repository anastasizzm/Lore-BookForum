# PDF.js (vendored)

This directory contains the runtime files of [PDF.js](https://github.com/mozilla/pdf.js)
used by the book reader (`public/assets/js/book-read.js`).

- Version: **4.10.38** (`pdfjs-dist` npm package)
- Files kept: `pdf.min.mjs`, `pdf.worker.min.mjs`, `cmaps/`, `standard_fonts/`, `LICENSE`
- Everything is served as static assets from `/assets/js/pdfjs/`.

To update, run from the repository root:

```bash
npm pack pdfjs-dist@<version>
tar -xzf pdfjs-dist-<version>.tgz
cp package/build/pdf.min.mjs package/build/pdf.worker.min.mjs public/assets/js/pdfjs/
rm -rf public/assets/js/pdfjs/cmaps public/assets/js/pdfjs/standard_fonts
cp -R package/cmaps package/standard_fonts public/assets/js/pdfjs/
cp package/LICENSE public/assets/js/pdfjs/LICENSE
```

Keep the import in `book-read.js` and `GlobalWorkerOptions.workerSrc` in sync with the
file names above.
