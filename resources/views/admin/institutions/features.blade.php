<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Feature Toggles - {{ $institution->name ?? 'Institution' }}</title>

  <!-- Axios CDN (pastikan dimuat sebelum script yang memakai axios) -->
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

  <style>
    body{ font-family: Inter, Arial; padding:20px; max-width:900px; margin:auto }
    .card{ background:#fff; border-radius:8px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.06) }
    .group{ margin-bottom:18px; padding:12px; border:1px solid #eee; border-radius:6px }
    .row{ display:flex; align-items:center; margin:6px 0 }
    .row label{ flex:1; }
    .row input[type="checkbox"]{ transform:scale(1.2) }
    .btn{ padding:8px 12px; cursor:pointer }
    .btn-primary{ background:#2563eb;color:#fff;border:none }
    .muted{ color:#666 }
  </style>
</head>
<body>
  <a href="/admin/institutions">← Back to Institutions</a>

  <div class="card" style="margin-top:16px;">
    <h1>Feature Toggles</h1>
    <div id="alerts" style="margin-top:8px"></div>

    <div id="form" style="margin-top:18px">
      <div class="muted">Loading features...</div>
    </div>

    <div style="margin-top:12px">
      <!-- Save button always present so addEventListener tidak gagal -->
      <button id="saveBtn" class="btn btn-primary">Save</button>
    </div>
  </div>

<script>
(function(){
  // CSRF header for axios (if meta exists)
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  if (window.axios && csrfMeta) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
  }

  // Try get institutionId from server-rendered var first
  let institutionId = @json($institution->id ?? null);

  // Safe helper to set innerHTML/textContent
  function setHtml(id, html) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = html;
  }
  function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  // Resolve institutionId from URL if server var empty
  function resolveIdFromUrl() {
    if (institutionId) return institutionId;
    const m = window.location.pathname.match(/\/admin\/institutions\/(\d+)\/features/);
    if (m && m[1]) return m[1];
    const qp = new URLSearchParams(window.location.search);
    const q = qp.get('id');
    if (q) return q;
    return null;
  }

  // Render error when id missing
  function showNoIdError() {
    setHtml('form', '<div style="color:#b00">Error: Institution ID not provided</div>');
    const btn = document.getElementById('saveBtn');
    if (btn) btn.disabled = true;
  }

  // Render features map into the form
  function renderFeatures(features) {
    const container = document.getElementById('form');
    if (!container) return;
    container.innerHTML = '';

    const groups = Object.keys(features || {});
    if (groups.length === 0) {
      container.innerHTML = '<div>No feature data available.</div>';
      return;
    }

    groups.forEach(g => {
      const groupEl = document.createElement('div');
      groupEl.className = 'group';
      const title = document.createElement('h3');
      title.textContent = g.charAt(0).toUpperCase() + g.slice(1);
      groupEl.appendChild(title);

      const keys = Object.keys(features[g] || {});
      keys.forEach(k => {
        const row = document.createElement('div');
        row.className = 'row';
        const label = document.createElement('label');
        label.textContent = k.replace(/_/g,' ');
        const chk = document.createElement('input');
        chk.type = 'checkbox';
        chk.checked = !!features[g][k];
        chk.onchange = () => { features[g][k] = chk.checked; };
        row.appendChild(label);
        row.appendChild(chk);
        groupEl.appendChild(row);
      });

      container.appendChild(groupEl);
    });
  }

  // Load features from API
  async function loadFeatures(id) {
    setHtml('form', '<div class="muted">Loading features...</div>');
    try {
      const res = await axios.get(`/api/institutions/${id}`);
      const data = res.data || {};
      const features = data.features || {};
      renderFeatures(features);
      setHtml('alerts', ''); // clear
    } catch (e) {
      console.error('Failed load features', e);
      setHtml('form', '<div style="color:red">Gagal load: ' + (e.response?.status || e.message) + '</div>');
    }
  }

  // Save flattened settings
  async function saveFeatures(id, features) {
    // flatten nested map to dotted keys
    const settings = {};
    Object.keys(features).forEach(g => {
      Object.keys(features[g] || {}).forEach(k => {
        settings[`${g}.${k}`] = features[g][k];
      });
    });

    try {
      const btn = document.getElementById('saveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Menyimpan...'; }
      await axios.post(`/api/institutions/${id}/settings`, { settings });
      setHtml('alerts', '<div style="color:green">Tersimpan.</div>');
    } catch (e) {
      console.error('Failed save settings', e);
      setHtml('alerts', '<div style="color:red">Gagal simpan: ' + (e.response?.status || e.message) + '</div>');
    } finally {
      const btn = document.getElementById('saveBtn');
      if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      // reload features to show fresh values
      await loadFeatures(id);
    }
  }

  // Kick off when DOM ready
  document.addEventListener('DOMContentLoaded', function() {
    institutionId = resolveIdFromUrl();
    if (!institutionId) {
      showNoIdError();
      return;
    }

    // load initial features into a variable we can reference when saving
    let currentFeatures = {};

    // initial load and keep currentFeatures updated by render step
    (async () => {
      try {
        const res = await axios.get(`/api/institutions/${institutionId}`);
        currentFeatures = res.data?.features || {};
        renderFeatures(currentFeatures);
      } catch (e) {
        console.error('initial load error', e);
        setHtml('form', '<div style="color:red">Gagal load: ' + (e.response?.status || e.message) + '</div>');
        return;
      }
    })();

    // Attach save handler (null-safe)
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
      saveBtn.addEventListener('click', function() {
        // ensure we get latest state from DOM-rendered checkboxes
        // rebuild currentFeatures from DOM
        const container = document.getElementById('form');
        const newFeatures = {};
        if (container) {
          const groups = container.querySelectorAll('.group');
          groups.forEach(groupEl => {
            const titleEl = groupEl.querySelector('h3');
            if (!titleEl) return;
            const grp = titleEl.textContent.trim().toLowerCase();
            newFeatures[grp] = {};
            const rows = groupEl.querySelectorAll('.row');
            rows.forEach(row => {
              const label = row.querySelector('label')?.textContent?.trim() || null;
              const key = label ? label.replace(/\s+/g, '_').toLowerCase() : null;
              const chk = row.querySelector('input[type="checkbox"]');
              if (key && chk) {
                newFeatures[grp][key] = !!chk.checked;
              }
            });
          });
        }
        // save using flattened keys
        saveFeatures(institutionId, newFeatures);
      });
    }
  });
})();
</script>
</body>
</html>