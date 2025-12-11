<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feature Toggles - {{ config('app.name', 'Admin') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 24px; 
            background: #f9fafb;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h2 { 
            margin-top: 0; 
            color: #1f2937;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 16px;
            color: #3b82f6;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .section {
            margin-bottom: 24px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .section h3 {
            margin-top: 0;
            margin-bottom: 12px;
            color: #374151;
            font-size: 18px;
        }
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .checkbox-item:hover {
            background: #f3f4f6;
        }
        .checkbox-item input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .checkbox-item label {
            cursor: pointer;
            font-size: 15px;
            color: #374151;
        }
        .actions {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        button {
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: background 0.2s;
        }
        button:hover {
            background: #2563eb;
        }
        button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .status {
            margin-left: 12px;
            font-size: 14px;
            color: #6b7280;
        }
        .status.success {
            color: #059669;
        }
        .status.error {
            color: #dc2626;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/admin/institutions" class="back-link">← Back to Institutions</a>
        
        <h2>Feature Toggles</h2>
        
        <div id="institution-info" class="info-box" style="display:none;">
            <strong id="inst-name"></strong> (<span id="inst-code"></span>)
        </div>

        <div id="loading" class="loading">Loading features...</div>
        
        <div id="features-form" style="display:none;">
            <div class="section">
                <h3>Application Features</h3>
                <div class="checkbox-group" id="features-group">
                    <!-- Will be populated dynamically -->
                </div>
            </div>

            <div class="section">
                <h3>Attendance Features</h3>
                <div class="checkbox-group" id="attendance-group">
                    <!-- Will be populated dynamically -->
                </div>
            </div>

            <div class="actions">
                <button onclick="saveFeatures()" id="save-btn">Save Changes</button>
                <span id="save-status" class="status"></span>
            </div>
        </div>
    </div>

    <script>
        const institutionId = {{ $institutionId ?? 'null' }};
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        let currentFeatures = {};

        async function loadFeatures() {
            if (!institutionId) {
                document.getElementById('loading').textContent = 'Error: Institution ID not provided';
                return;
            }

            try {
                const res = await axios.get(`/api/institutions/${institutionId}`);
                const { institution, features } = res.data;

                // Show institution info
                document.getElementById('inst-name').textContent = institution.name;
                document.getElementById('inst-code').textContent = institution.code;
                document.getElementById('institution-info').style.display = 'block';

                currentFeatures = features;

                // Render features checkboxes
                renderFeatureGroup('features', features.features || {}, 'features-group');
                renderFeatureGroup('attendance', features.attendance || {}, 'attendance-group');

                // Show form, hide loading
                document.getElementById('loading').style.display = 'none';
                document.getElementById('features-form').style.display = 'block';
            } catch (err) {
                console.error(err);
                document.getElementById('loading').textContent = 
                    `Error loading features: ${err.response?.status || err.message}`;
            }
        }

        function renderFeatureGroup(prefix, features, containerId) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';

            for (const [key, value] of Object.entries(features)) {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'checkbox-item';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = `${prefix}.${key}`;
                checkbox.checked = value === true;
                checkbox.dataset.prefix = prefix;
                checkbox.dataset.key = key;

                const label = document.createElement('label');
                label.htmlFor = `${prefix}.${key}`;
                label.textContent = formatLabel(key);

                itemDiv.appendChild(checkbox);
                itemDiv.appendChild(label);
                container.appendChild(itemDiv);
            }
        }

        function formatLabel(key) {
            return key
                .split('_')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }

        async function saveFeatures() {
            const saveBtn = document.getElementById('save-btn');
            const statusEl = document.getElementById('save-status');

            saveBtn.disabled = true;
            statusEl.textContent = 'Saving...';
            statusEl.className = 'status';

            try {
                // Collect all checkbox values into flat key structure
                const settings = {};
                const checkboxes = document.querySelectorAll('input[type="checkbox"]');
                
                checkboxes.forEach(cb => {
                    const prefix = cb.dataset.prefix;
                    const key = cb.dataset.key;
                    const fullKey = `${prefix}.${key}`;
                    settings[fullKey] = cb.checked;
                });

                // Send to API
                await axios.post(`/api/institutions/${institutionId}/settings`, 
                    { settings }, 
                    { headers: { 'X-CSRF-TOKEN': csrf } }
                );

                statusEl.textContent = 'Saved successfully!';
                statusEl.className = 'status success';

                // Reload to show updated values
                setTimeout(() => {
                    loadFeatures();
                    statusEl.textContent = '';
                }, 2000);
            } catch (err) {
                console.error(err);
                statusEl.textContent = `Save failed: ${err.response?.status || err.message}`;
                statusEl.className = 'status error';
            } finally {
                saveBtn.disabled = false;
            }
        }

        document.addEventListener('DOMContentLoaded', loadFeatures);
    </script>
</body>
</html>
