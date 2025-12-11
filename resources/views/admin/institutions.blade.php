<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Institutions</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        table { border-collapse: collapse; width: 100%; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #f4f4f4; text-align: left; }
        button { padding: 6px 10px; margin-right: 4px; }
        .top-actions { margin-bottom: 12px; }
        .error { color: red; margin-top: 8px; }
    </style>
</head>
<body>
    <h2>Institutions</h2>

    <div class="top-actions">
        <a href="/admin/institutions/create"><button>Create App</button></a>
    </div>

    <div id="list">Loading...</div>

    <script>
    async function fetchList() {
        try {
            const res = await axios.get('/api/institutions');
            const items = Array.isArray(res.data) ? res.data : (res.data.data || []);

            if (!items.length) {
                document.getElementById('list').innerHTML = '<div>Tidak ada data.</div>';
                return;
            }

            let html = '<table><thead><tr>' +
                '<th>Name</th><th>Code</th><th>Status</th><th>Actions</th>' +
                '</tr></thead><tbody>';

            items.forEach(i => {
                html += `<tr>
                    <td>${i.name}</td>
                    <td>${i.code}</td>
                    <td>${i.is_active ? 'Active' : 'Inactive'}</td>
                    <td>
                        <a href="/admin/institutions/${i.id}/configure"><button>Manage</button></a>
                        <a href="/admin/institutions/${i.id}/features"><button>Features</button></a>
                        <a href="/admin/institutions/${i.id}/edit"><button>Edit</button></a>
                        <button onclick="generate(${i.id})">Generate APK</button>
                    </td>
                </tr>`;
            });

            html += '</tbody></table>';
            document.getElementById('list').innerHTML = html;
        } catch (err) {
            console.error(err);
            document.getElementById('list').innerHTML =
                `<div class="error">Gagal mengambil data: ${err.response?.status || err.message}</div>`;
        }
    }

    async function generate(id) {
        alert('Generate APK untuk ID ' + id + ' (implementasi API generate di backend).');
    }

    document.addEventListener('DOMContentLoaded', fetchList);
    </script>
</body>
</html>