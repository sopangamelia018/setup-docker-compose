const { WebSocketServer, WebSocket } = require('ws');
const mqtt = require('mqtt');

const PORT = Number(process.env.WS_PORT || 8090);
const MQTT_URL = process.env.MQTT_URL || 'mqtt://mosquitto:1883';

const wss = new WebSocketServer({ port: PORT });

function broadcast(payload) {
  const data = JSON.stringify(payload);
  wss.clients.forEach((c) => {
    if (c.readyState === WebSocket.OPEN) c.send(data);
  });
}

wss.on('connection', (ws) => {
  ws.send(JSON.stringify({ type: 'welcome', clients: wss.clients.size }));
  broadcast({ type: 'info', message: `Client terhubung. Total: ${wss.clients.size}` });

  ws.on('message', (raw) => broadcast({ type: 'chat', data: raw.toString() }));
  ws.on('close', () => broadcast({ type: 'info', message: `Client keluar. Total: ${wss.clients.size}` }));
});

// Bridge: semua pesan MQTT di topik demo/# diteruskan ke semua client WebSocket
const client = mqtt.connect(MQTT_URL);
client.on('connect', () => {
  console.log('[mqtt] connected ->', MQTT_URL);
  client.subscribe('demo/#');
});
client.on('message', (topic, payload) => {
  broadcast({ type: 'mqtt', topic, data: payload.toString() });
});
client.on('error', (e) => console.error('[mqtt] error:', e.message));

console.log(`[ws] listening on :${PORT}`);
