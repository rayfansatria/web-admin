<!doctype html>
<html>
<head><meta name="csrf-token" content="{{ csrf_token() }}"><title>Login</title></head>
<body style="font-family:Arial;max-width:480px;margin:40px auto">
  <h2>Login Admin</h2>

  @if($errors->any())
    <div style="color:red;margin-bottom:12px">
      {{ $errors->first() }}
    </div>
  @endif

  <form method="POST" action="{{ route('login.post') }}">
    @csrf
    <div style="margin-bottom:8px">
      <label>Email</label><br>
      <input type="email" name="email" required style="width:100%;padding:8px"/>
    </div>
    <div style="margin-bottom:8px">
      <label>Password</label><br>
      <input type="password" name="password" required style="width:100%;padding:8px"/>
    </div>
    <div>
      <button type="submit" style="padding:8px 12px">Login</button>
    </div>
  </form>
</body>
</html>