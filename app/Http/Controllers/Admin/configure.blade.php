<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Configure Institution</title>
  <style>
    body{ font-family: Inter, Arial; padding:20px; max-width:900px; margin:auto }
    label{ display:block; margin-top:12px }
    input[type="text"], select{ padding:8px; width:100%; max-width:420px }
    .toggle{ display:inline-block; margin-right:12px }
    .build-list{ margin-top:16px }
    .build-item{ border:1px solid #eee; padding:10px; margin-bottom:8px; }
    .btn{ padding:8px 12px; cursor:pointer }
    .btn-primary{ background:#2563eb;color:#fff;border:none }
  </style>
</head>
<body>
  <h1 id="title">Configure</h1>
  <div>
    <label>Nama Instansi
      <input id="name" type="text"/>
    </label>
    <label>Kode
      <input id="code" type="text"/>
    </label>
    <label>Timezone
      <input id="timezone" type="text"/>
    </label>

    <h3>Feature Toggles</h3>
    <div>
      <label class="toggle"><input type="checkbox" id="attendance_allow_mobile"/> Attendance (mobile)</label>
      <label class="toggle"><input type="checkbox" id="attendance_require_photo"/> Require Photo</label>
      <label class="toggle"><input type="checkbox" id="liveness_detection"/> Liveness</label>
    </div>

    <div style="margin-top:12px">
      <button class="btn btn-primary" onclick="saveSettings()">Save Settings</button>
      <button class="btn" onclick="generate()">Generate APK</button>
      <a href="/admin/institutions" style="margin-left:8px">Back to list</a>
    </div>

    <h3 class="build-list">Build history</h3>
    <div id="builds"></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <script>
    axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const pathParts = window.location.pathname.split('/');
    const institutionId = pathParts[pathParts.length-2] === 'institutions' ? pathParts[pathParts.length-1] : (new URLSearchParams(window.location.search).get('id'));
    // if route is /admin/institutions/{id}/configure, adapt
    const idFromUrl = (function(){
      // try pattern /admin/institutions/{id}/configure
      const m = window.location.pathname.match(/\/admin\/institutions\/(\d+)\/configure/);
      return m ? m[1] : null;
    })();
    const id = idFromUrl || institutionId;

    if(!id){ document.body.innerHTML = '<p>Missing institution id in URL</p>'; }

    async function load(){
      try {
        const res = await axios.get(`/api/institutions/${id}`);
        const inst = res.data.institution;
        const settings = res.data.settings || {};
        document.getElementById('title').innerText = 'Configure: ' + inst.name;
        document.getElementById('name').value = inst.name || '';
        document.getElementById('code').value = inst.code || '';
        document.getElementById('timezone').value = inst.timezone || '';
        document.getElementById('attendance_allow_mobile').checked = !!(settings['attendance.allow_mobile'] === true || settings['attendance.allow_mobile'] === 'true' || settings['features.attendance.allow_mobile'] === true);
        document.getElementById('attendance_require_photo').checked = !!(settings['attendance.require_photo'] === true || settings['attendance.require_photo'] === 'true');
        document.getElementById('liveness_detection').checked = !!(settings['attendance.liveness_detection'] === true || settings['attendance.liveness_detection'] === 'true');
      } catch (e) {
        alert('Gagal load instansi: '+ (e.response?.data?.message || e.message));
      }
      await loadBuilds();
    }

    async function saveSettings(){
      const payload = {
        name: document.getElementById('name').value,
        code: document.getElementById('code').value,
        timezone: document.getElementById('timezone').value
      };
      try {
        await axios.put(`/api/institutions/${id}`, payload);
      } catch(e){
        alert('Gagal simpan basic info: ' + (e.response?.data?.message || e.message));
        return;
      }

      const settings = {
        'attendance.allow_mobile': document.getElementById('attendance_allow_mobile').checked,
        'attendance.require_photo': document.getElementById('attendance_require_photo').checked,
        'attendance.liveness_detection': document.getElementById('liveness_detection').checked,
        'features.attendance.enabled': true
      };

      try {
        await axios.put(`/api/institutions/${id}/settings`, { settings });
        alert('Settings saved');
      } catch(e){
        alert('Gagal simpan settings: ' + (e.response?.data?.message || e.message));
      }
      await loadBuilds();
    }

    async function generate(){
      if(!confirm('Generate APK sekarang?')) return;
      try {
        const res = await axios.post(`/api/institutions/${id}/generate-app`, { platform:'android' });
        alert('Build queued: ' + (res.data.build_id || 'unknown'));
      } catch(e){
        alert('Gagal enqueue: ' + (e.response?.data?.message || e.message));
      }
      await loadBuilds();
    }

    async function loadBuilds(){
      try {
        const res = await axios.get(`/api/institutions/${id}`); // use same endpoint to get settings; ideally have app_builds API
        // For demo: query app_builds table via dedicated API would be better. We'll call /api/app-builds?institution_id=
        const buildsRes = await axios.get(`/api/app-builds?institution_id=${id}`).catch(()=>({data:[]}));
        const builds = buildsRes.data.data || buildsRes.data || [];
        let html = '';
        if(builds.length === 0) html = '<p>No builds yet</p>';
        builds.forEach(b=>{
          html += `<div class="build-item">
            <strong>#${b.id}</strong> status: ${b.status} - created: ${b.created_at || b.created}
            ${b.artifact_url ? `<div><a href="${b.artifact_url}" target="_blank">Download APK</a></div>` : ''}
            <div style="margin-top:6px">Log: <small>${(b.build_log||'')}</small></div>
          </div>`;
        });
        document.getElementById('builds').innerHTML = html;
      } catch(e){
        console.error(e);
      }
    }

    load();
  </script>
</body>
</html>