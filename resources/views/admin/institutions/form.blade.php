```blade
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $mode === 'create' ? 'Create Institution' : 'Edit Institution' }}</title>
  <style>
    body{ font-family: Inter, Arial; padding:20px; max-width:900px; margin:auto }
    label{ display:block; margin-top:12px }
    input[type="text"], select{ padding:8px; width:100%; max-width:420px }
    .btn{ padding:8px 12px; cursor:pointer; margin-top:12px }
    .btn-primary{ background:#2563eb;color:#fff;border:none }
  </style>
</head>
<body>
  <h1>{{ $mode === 'create' ? 'Create Application (Institution)' : 'Edit Application' }}</h1>

  <form method="POST" action="{{ $mode === 'create' ? route('admin.institutions.store') : route('admin.institutions.update', ['id' => $institution->id]) }}">
    @csrf

    <label>Nama Instansi
      <input name="name" type="text" value="{{ old('name', $institution->name) }}" required/>
    </label>

    <label>Kode (unique)
      <input name="code" type="text" value="{{ old('code', $institution->code) }}" required/>
    </label>

    <label>Timezone
      <input name="timezone" type="text" value="{{ old('timezone', $institution->timezone ?? 'Asia/Jakarta') }}" />
    </label>

    <label>Logo URL
      <input name="logo_url" type="text" value="{{ old('logo_url', $institution->logo_url) }}" />
    </label>

    <label>
      <input type="checkbox" name="is_active" value="1" {{ old('is_active', $institution->is_active ?? true) ? 'checked' : '' }} />
      Active
    </label>

    <div>
      <button class="btn btn-primary" type="submit">{{ $mode === 'create' ? 'Create' : 'Save' }}</button>
      <a href="/admin/institutions" style="margin-left:8px"><button type="button" class="btn">Back</button></a>
    </div>
  </form>
</body>
</html>