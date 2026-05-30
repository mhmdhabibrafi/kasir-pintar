# Backup Directory

Folder ini hanya untuk output backup lokal/runtime.

- Jangan upload file `.sql`, `.sql.gz`, credential, atau dump database ke repository atau paket deploy.
- Untuk production, arahkan document root web server langsung ke folder `public/`.
- `.htaccess` tetap disediakan sebagai proteksi tambahan untuk server Apache yang masih melayani root project.
