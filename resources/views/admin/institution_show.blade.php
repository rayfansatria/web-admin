<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Configure Institution</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        h2 { margin-bottom: 8px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, select { padding: 6px; width: 320px; }
        .row { margin-bottom: 6px; }
        .actions { margin-top: 14px; }
        button { padding: 8px 12px; margin-right: 8px; }
        .status { margin-left: 10px; }
        pre { background: #f5f5f5; padding: 8px; }
    </style>
</head>
<body>
    <h2>Configure: {{ $inst->name }} ({{ $inst->code }})</h2>

    <div id="info" class="row">
        <div><b>Timezone:</b> <span id="tz-info">-</span></div>
        <div><b>Status:</b> <span id="status-info">-</span></div>
    </div>

    <h3>App Settings</h3>
    <div class="row">
        <label>Timezone</label>
        <input id="timezone" placeholder="Asia/Jakarta">
    </div>
    <div class="row">
        <label>Logo URL</label>
        <input id="logo_url" placeholder="https://...">
    </div>
    <div class="row">
        <label>Primary Color</label>
        <input id="primary_color" placeholder="#0F172A">
    </div>
    <div class="row">
        <label>Secondary Color</label>
        <input id="secondary_color" placeholder="#22C55E">
    </div>
    <div class="row">
        <label>Package Name (Android)</label>
        <input id="package_name" placeholder="com.example.app">
    </div>
    <div class="row">
        <label>App Name</label>
        <input id="app_name" placeholder="My App">
    </div>

    <div class="actions">
        <button onclick="saveSettings()">Simpan</button>
        <select id="platform">
            <option value="android">Android</option>
        </select>
        <button onclick="generate()">Generate APK</button>
        <span id="save-status" class="status"></span>
    </div>

    <div class="actions" style="margin-top:10px;">
        <button onclick="checkStatus()">Cek Status</button>
        <span id="status-text"></span>
    </div>

    <h4>Raw Settings (debug)</h4>
    <pre id="raw">Loading...</pre>

    <p><a href="/admin/institutions">← Kembali</a></p>

<script>
const id = {{ $inst->id }};
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let currentBuildId = null; // simpan build id terbaru

async function load() {
    try {
        const res = await axios.get(`/api/institutions/${id}`);
        const inst = res.data.institution;
        const s = res.data.settings || {};

        document.getElementById('tz-info').textContent = inst.timezone || '-';
        document.getElementById('status-info').textContent = inst.is_active ? 'Active' : 'Inactive';

        document.getElementById('timezone').value = s.timezone || inst.timezone || 'Asia/Jakarta';
        document.getElementById('logo_url').value = s.logo_url || '';
        document.getElementById('primary_color').value = s.primary_color || '#0F172A';
        document.getElementById('secondary_color').value = s.secondary_color || '#22C55E';
        document.getElementById('package_name').value = s.package_name || 'com.example.app';
        document.getElementById('app_name').value = s.app_name || inst.name || 'My App';

        document.getElementById('raw').textContent = JSON.stringify(s, null, 2);
    } catch (err) {
        document.getElementById('raw').textContent = 'Gagal load: ' + (err.response?.status || err.message);
    }
}

async function saveSettings() {
    try {
        document.getElementById('save-status').textContent = 'Menyimpan...';
        const payload = {
            settings: {
                timezone: document.getElementById('timezone').value,
                logo_url: document.getElementById('logo_url').value,
                primary_color: document.getElementById('primary_color').value,
                secondary_color: document.getElementById('secondary_color').value,
                package_name: document.getElementById('package_name').value,
                app_name: document.getElementById('app_name').value,
            }
        };
        await axios.post(`/api/institutions/${id}/settings`, payload, { headers: { 'X-CSRF-TOKEN': csrf } });
        document.getElementById('save-status').textContent = 'Tersimpan.';
        load();
    } catch (err) {
        document.getElementById('save-status').textContent = 'Gagal: ' + (err.response?.status || err.message);
    }
}

async function generate() {
    try {
        document.getElementById('save-status').textContent = 'Memicu build...';
        const platform = document.getElementById('platform').value;
        const res = await axios.post(`/api/institutions/${id}/generate-app`, { platform }, { headers: { 'X-CSRF-TOKEN': csrf } });
        currentBuildId = res.data.build_id; // simpan build id
        document.getElementById('save-status').textContent = 'Build dipicu. build_id: ' + currentBuildId;
    } catch (err) {
        document.getElementById('save-status').textContent = 'Gagal trigger build: ' + (err.response?.status || err.message);
    }
}

async function checkStatus() {
  if (!currentBuildId) {
    document.getElementById('status-text').textContent =
      'Gagal cek status: currentBuildId belum ada (klik Generate dulu).';
    return;
  }
  try {
    const res = await axios.get(`/api/builds/${currentBuildId}`);
    const b = res.data;
    const link = b.apk_url
      ? ` | <a href="${b.apk_url}" target="_blank" rel="noopener">Download APK</a>`
      : '';
    document.getElementById('status-text').innerHTML = `Status: ${b.status}${link}`;
  } catch (err) {
    document.getElementById('status-text').textContent =
      'Gagal cek status: ' + (err.response?.status || err.message);
  }
}

document.addEventListener('DOMContentLoaded', load);
</script>
</body>
</html>