import makeWASocket, { DisconnectReason, useMultiFileAuthState } from '@whiskeysockets/baileys'
import P from 'pino'
import qr from 'qrcode-terminal'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { extractText, senderPhone, validConfig } from './bridge.js'

// Read only local configuration; don't print secrets or session files.
try {
  for (const line of readFileSync(resolve('.env'), 'utf8').split(/\r?\n/)) {
    const match = line.match(/^([A-Z_]+)=(.*)$/)
    if (match && !process.env[match[1]]) process.env[match[1]] = match[2].trim()
  }
} catch { /* variables may come from the process environment */ }
const base = process.env.CRM_URL ?? 'http://127.0.0.1:8000'
const secret = process.env.BAILEYS_DEMO_SECRET
if (!validConfig(base, secret)) throw new Error('Set loopback CRM_URL and BAILEYS_DEMO_SECRET (32+ chars).')
const allowed = new Set((process.env.DEMO_ALLOWLIST ?? '').split(',').map(x => x.trim()).filter(Boolean))
const openMode = process.env.DEMO_OPEN_MODE === 'true'
if (!allowed.size && !openMode) throw new Error('Set DEMO_ALLOWLIST to dedicated tester numbers before starting.')
console.log(openMode ? 'OPEN DEMO MODE: direct messages from any number are accepted.' : 'Tester allowlist mode enabled.')

const { state, saveCreds } = await useMultiFileAuthState(resolve('auth'))
let socket
let reconnectTimer
async function connect() {
  socket = makeWASocket({ auth: state, logger: P({ level: 'silent' }), printQRInTerminal: false, markOnlineOnConnect: false })
  socket.ev.on('creds.update', saveCreds)
  socket.ev.on('connection.update', ({ connection, qr: qrText, lastDisconnect }) => {
    if (qrText) qr.generate(qrText, { small: true })
    if (connection === 'open') console.log('Demo WhatsApp connected')
    if (connection === 'close') {
      const code = lastDisconnect?.error?.output?.statusCode
      console.warn('Demo WhatsApp disconnected; status:', code ?? 'unknown')
      if (code === DisconnectReason.loggedOut) { console.error('Logged out. Stop and re-pair only with your demo number.'); return }
      clearTimeout(reconnectTimer)
      reconnectTimer = setTimeout(connect, 3000)
    }
  })
  socket.ev.on('messages.upsert', async ({ messages, type }) => {
    if (type !== 'notify') return
    for (const message of messages) {
      const jid = message.key?.remoteJid
      if (message.key?.fromMe || !message.key?.id) continue
      let phone
      try { phone = await senderPhone(message.key, socket.signalRepository?.lidMapping) }
      catch { console.warn('Could not resolve sender identity; skipped message'); continue }
      if (!phone) { console.log('Skipped message: unsupported sender identity'); continue }
      if (!openMode && !allowed.has(phone)) { console.log('Skipped message: sender not in tester allowlist'); continue }
      const text = extractText(message.message)
      if (!text) { console.log('Skipped message: not supported text'); continue }
      try {
        const response = await fetch(new URL('/internal/baileys-demo/message', base), {
          method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Baileys-Secret': secret },
          body: JSON.stringify({ phone, message_id: message.key.id, text }), signal: AbortSignal.timeout(10000),
        })
        if (!response.ok) { console.error('CRM rejected message:', response.status); continue }
        const data = await response.json()
        if (typeof data.reply === 'string' && data.reply.length) {
          try {
            await socket.sendMessage(jid, { text: data.reply })
          } catch {
            // The CRM already advanced its state. Do not retry automatically: a timeout
            // can mean WhatsApp accepted the reply even though the acknowledgement failed.
            console.error('WhatsApp reply delivery uncertain. Inspect the chat manually; do not resend blindly.')
          }
        }
      } catch {
        console.error('CRM communication failed. Check Laravel/MySQL; no automatic reply retry.')
      }
    }
  })
}
await connect()
