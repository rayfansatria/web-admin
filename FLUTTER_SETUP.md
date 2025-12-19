# Flutter Template + APK Absensi Guide

## Setup Flutter Template Project

Untuk generate APK, Anda perlu membuat Flutter template project yang akan dikustomisasi untuk setiap institution.

### 1. Buat Flutter Template

```bash
# Buat project Flutter di folder sejajar dengan Laravel
cd c:\laragon\www
flutter create flutter-template

cd flutter-template
```

### 2. Struktur Project yang Disarankan

```
flutter_template_dips/
├── android/
│   └── app/
│       ├── build.gradle           # Akan dimodifikasi (applicationId)
│       └── src/main/
│           └── AndroidManifest.xml # Akan dimodifikasi (package, label)
├── assets/                         # Akan dibuat otomatis
│   └── config.json                # Dibuat otomatis oleh Laravel builder
├── lib/
│   ├── main.dart                  # Entry point
│   ├── theme/
│   │   └── app_colors.dart       # Theme colors (digenerate builder)
│   └── config/
│       └── app_config.dart       # Loader untuk assets/config.json
├── pubspec.yaml                   # Akan dimodifikasi (name, version)
└── build/                         # Output APK ada di sini
```

### 3. Update main.dart untuk Load Config

Buat file `lib/config/app_config.dart`:

```dart
import 'dart:convert';
import 'package:flutter/services.dart';

class AppConfig {
  final Map<String, dynamic> data;

  AppConfig(this.data);

  static Future<AppConfig> load() async {
    try {
      final configString = await rootBundle.loadString('assets/config.json');
      final configData = json.decode(configString);
      return AppConfig(configData);
    } catch (e) {
      print('Error loading config: $e');
      return AppConfig({});
    }
  }

  String get appName => data['institution']?['name'] ?? 'App';
  String get institutionCode => data['institution']?['code'] ?? '';
  Map<String, dynamic> get features => data['features'] ?? {};
  Map<String, dynamic> get branding => data['branding'] ?? {};
}
```

Update `lib/main.dart`:

```dart
import 'package:flutter/material.dart';
import 'config/app_config.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Load config dari assets
  final config = await AppConfig.load();
  
  runApp(MyApp(config: config));
}

class MyApp extends StatelessWidget {
  final AppConfig config;

  const MyApp({Key? key, required this.config}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: config.appName,
      theme: ThemeData(
        primarySwatch: Colors.blue,
        visualDensity: VisualDensity.adaptivePlatformDensity,
      ),
      home: HomePage(config: config),
    );
  }
}

class HomePage extends StatelessWidget {
  final AppConfig config;

  const HomePage({Key? key, required this.config}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(config.appName),
      ),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              'Welcome to ${config.appName}',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            SizedBox(height: 20),
            Text('Institution: ${config.institutionCode}'),
            SizedBox(height: 10),
            Text('Features:'),
            ...config.features.entries.map((e) => 
              Text('  ${e.key}: ${e.value}')
            ).toList(),
          ],
        ),
      ),
    );
  }
}
```

### 4. Update pubspec.yaml

Tambahkan assets section:

```yaml
flutter:
  uses-material-design: true
  
  assets:
    - assets/
```

### 5. Install Dependencies

```bash
cd c:\laragon\www\flutter-template
flutter pub get
```

### 6. Test Build Manually

```bash
flutter build apk --release
```

APK akan ada di: `build/app/outputs/flutter-apk/app-release.apk`

### 7. Konfigurasi Laravel

Tambahkan ke `.env`:

```env
# Flutter Build Configuration
FLUTTER_TEMPLATE_PATH="C:/laragon/www/flutter_template_dips"
FLUTTER_CMD="C:/Users/rayfa/flutter/bin/flutter.bat"  # sesuaikan dgn path Flutter kamu

# Atau jika flutter tidak di PATH, gunakan full path:
# FLUTTER_CMD="C:/flutter/bin/flutter.bat"
```

### 8. Setup Storage Link

```bash
cd c:\laragon\www\admin-dipo-laravel
php artisan storage:link
```

### 9. Configure Queue

Untuk production, gunakan queue worker:

```bash
# Option 1: Database queue (simple)
php artisan queue:work

# Option 2: Redis queue (recommended for production)
# Update .env:
# QUEUE_CONNECTION=redis
```

Untuk development, bisa gunakan sync queue di `.env`:
```env
QUEUE_CONNECTION=sync
```

## Testing Build Process

1. Login ke admin panel: http://127.0.0.1:8000/admin/institutions
2. Pilih institution → Configure
3. Isi settings (app name, colors, dll)
4. Klik **Generate APK**
5. Tunggu beberapa menit (build process memakan waktu 3-10 menit)
6. Klik **Cek Status** untuk melihat progress
7. Download APK saat status = success

## Bikin APK Absensi (End-to-End)

Agar APK “Absensi” mengikuti setting dari web admin, alurnya seperti ini:

- Di web admin, buka halaman Institution → Configure dan Features.
- Simpan setting berikut (kami sudah allow-list di backend):
  - Fitur: `features.attendance.enabled` (nyalakan untuk aktifkan modul absensi)
  - Absensi: `attendance.allow_mobile`, `attendance.require_photo`, `attendance.require_location`, `attendance.liveness_detection`
  - Branding: `branding.primaryColor`, `branding.logoUrl` (opsional)
  - Build: `build.version`, `build.platform` (opsional; default android)

Backend kami akan menghasilkan `assets/config.json` berisi konfigurasi ini dan menuliskannya ke template saat build.

Di sisi Flutter app:
- Pastikan ada loader `lib/config/app_config.dart` yang membaca `assets/config.json` (contoh di atas).
- Pada startup (di `main.dart`), load config lalu pass ke `MyApp`.
- Tampilkan/aktifkan fitur absensi berdasarkan `config.features.attendance.*` dan opsi di `config['attendance']`.

Contoh struktur config (ringkas):

```json
{
  "institution": {"id": 1, "code": "ABC", "name": "PT ABC"},
  "features": {
    "attendance": {"enabled": true}
  },
  "attendance": {
    "allow_mobile": true,
    "require_photo": false,
    "require_location": true,
    "liveness_detection": false
  },
  "branding": {
    "primaryColor": "#3B82F6",
    "logoUrl": "https://.../logo.png"
  },
  "build": {
    "bundleIdentifier": "com.example.absensi.abc",
    "version": "1.0.0"
  }
}
```

Dengan pola ini, setiap kali kamu ubah setting di web dan klik Generate APK, APK Absensi akan otomatis mengikuti konfigurasi terbaru tanpa mengubah kode Flutter manual.

## Troubleshooting

### Flutter Command Not Found
```bash
# Windows: Tambahkan Flutter ke PATH atau gunakan full path di .env
FLUTTER_CMD="C:/Users/rayfa/flutter/bin/flutter.bat"
```

### Permission Denied
```bash
# Pastikan folder storage writable
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Build Timeout
Update `config/queue.php`:
```php
'timeout' => 900, // 15 minutes
```

### APK Not Found After Build
Check logs:
```bash
php artisan queue:listen --verbose
tail -f storage/logs/laravel.log
```

## Advanced: Docker Build (Optional)

Untuk build environment yang isolated, bisa gunakan Docker:

```dockerfile
# Dockerfile.flutter
FROM cirrusci/flutter:stable

WORKDIR /app
COPY . .

RUN flutter pub get
RUN flutter build apk --release
```

Update FlutterAppBuilder untuk use Docker jika diperlukan.
