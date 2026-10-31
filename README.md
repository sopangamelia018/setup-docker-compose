# Docker Compose Dev Stack

Web development stack: SQL + NoSQL, API PHP & Python, frontend Node.js, tools admin DB, WebSocket, MQTT, RabbitMQ.

## Cara menjalankan
```bash
cp .env .env.local   # opsional, ubah password sesuai kebutuhan
docker compose up -d --build
docker compose ps
docker compose logs -f python-api   # lihat log satu service
docker compose down                 # stop (data tetap ada)
docker compose down -v              # stop + HAPUS semua data volume
```

## Daftar service & akses
| Service | URL / Port | Login |
|---|---|---|
| Frontend (Node.js) | http://localhost:3000 | - |
| PHP API | http://localhost:8001 (`/health`) | - |
| Python API (FastAPI) | http://localhost:8002 (`/docs`, `/health`) | - |
| WebSocket | ws://localhost:8090 | - |
| MQTT (TCP) | localhost:1883 | anonymous |
| MQTT over WebSocket | ws://localhost:9001 | anonymous |
| RabbitMQ AMQP | localhost:5672 | guest / guest |
| RabbitMQ UI | http://localhost:15672 | guest / guest |
| phpMyAdmin | http://localhost:8081 | appuser / apppass (atau root / rootsecret) |
| pgAdmin | http://localhost:5050 | admin@example.com / admin123 |
| Mongo Express | http://localhost:8082 | admin / admin123 |
| MySQL | localhost:3306 | appuser / apppass |
| PostgreSQL | localhost:5432 | appuser / apppass |
| MongoDB | localhost:27017 | root / rootsecret |

Semua nilai di atas diatur di file `.env`.

## Menghubungkan antar-container
Gunakan **nama service** sebagai host, bukan localhost:
`mysql`, `postgres`, `mongo`, `rabbitmq`, `mosquitto`, `websocket`.

## Pengujian cepat
```bash
curl http://localhost:8001/health
curl http://localhost:8002/health
curl -X POST http://localhost:8001/rabbitmq/publish -H "Content-Type: application/json" -d '{"message":"halo php"}'
curl -X POST http://localhost:8002/mqtt/publish -H "Content-Type: application/json" -d '{"message":"halo mqtt"}'
```
Lalu buka http://localhost:3000 untuk uji WebSocket, MQTT, dan RabbitMQ dari browser.
Pesan MQTT pada topik `demo/#` otomatis diteruskan ke semua client WebSocket (bridge di service `websocket`).

## Catatan
- pgAdmin: saat pertama membuka server "PostgreSQL Dev", masukkan password `POSTGRES_PASSWORD`.
  Jika `POSTGRES_USER` diubah, sesuaikan juga `pgadmin/servers.json`.
- Kode PHP/Python/Frontend di-*mount* sebagai volume, jadi perubahan kode langsung ter-reload.
- Untuk PRODUCTION: ganti semua password, matikan `allow_anonymous` di Mosquitto, jangan expose port database/admin ke publik, tambahkan TLS.
