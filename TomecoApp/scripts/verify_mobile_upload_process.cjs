const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
  const filename = '_mobile_runtime_verify_01a130b5.php';
  const probe = path.resolve(__dirname, '../public', filename);
  fs.writeFileSync(probe, `<?php
header('Content-Type: application/json');
echo json_encode(['sapi' => PHP_SAPI, 'upload_max_filesize' => ini_get('upload_max_filesize'),
'post_max_size' => ini_get('post_max_size'), 'max_file_uploads' => ini_get('max_file_uploads'), 'files' => $_FILES]);
`, { flag: 'wx' });
  try {
  for (const root of ['http://192.168.5.178:8000/', 'http://127.0.0.1/Capstone/TomecoApp/public/']) {
    const form = new FormData();
    const photo = new Blob([new Uint8Array(8 * 1024 * 1024)], { type: 'image/jpeg' });
    for (let i = 0; i < 5; i++) form.append('evidence_images[]', photo, `photo-${i}.jpg`);
    const response = await fetch(root + filename, { method: 'POST', body: form });
    assert.equal(response.status, 200);
    const settings = await response.json();
    assert.equal(settings.upload_max_filesize, '8M');
    assert.equal(settings.post_max_size, '64M');
    assert.ok(Number(settings.max_file_uploads) >= 5);
    assert.equal(settings.files.evidence_image, undefined);
    assert.deepEqual(settings.files.evidence_images.error, [0, 0, 0, 0, 0]);
    assert.deepEqual(settings.files.evidence_images.size, Array(5).fill(8388608));
    console.log(JSON.stringify({ root, sapi: settings.sapi, upload: settings.upload_max_filesize, post: settings.post_max_size, fileParts: settings.max_file_uploads, uploadedParts: 5, bytesPerPart: 8388608 }));
  }
  } finally { fs.unlinkSync(probe); }
})().catch(e => { console.error(e); process.exit(1); });
