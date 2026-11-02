import test from 'node:test'
import assert from 'node:assert/strict'
import { phoneFromJid, senderPhone, extractText, validConfig } from './bridge.js'

test('only direct individual chats are accepted', () => {
  assert.equal(phoneFromJid('628123456789@s.whatsapp.net'), '628123456789')
  assert.equal(phoneFromJid('123@g.us'), null)
  assert.equal(phoneFromJid('status@broadcast'), null)
})
test('LID direct chats resolve only to a phone-number identity', async () => {
  const mapping = { getPNForLID: async jid => jid === '123456789@lid' ? '628123456789@s.whatsapp.net' : null }
  assert.equal(await senderPhone({ remoteJid: '123456789@lid' }, mapping), '628123456789')
  assert.equal(await senderPhone({ remoteJid: '123456789@lid', remoteJidAlt: '628777777777@s.whatsapp.net' }, mapping), '628777777777')
  assert.equal(await senderPhone({ remoteJid: '123456789@lid' }, null), null)
  assert.equal(await senderPhone({ remoteJid: '123@g.us', remoteJidAlt: '628123456789@s.whatsapp.net' }, mapping), null)
  assert.equal(await senderPhone({ remoteJid: '628123456789@s.whatsapp.net', fromMe: true }, mapping), null)
})
test('only short text messages are processed', () => {
  assert.equal(extractText({ conversation: ' MENU ' }), 'MENU')
  assert.equal(extractText({ ephemeralMessage: { message: { extendedTextMessage: { text: 'CS' } } } }), 'CS')
  assert.equal(extractText({ imageMessage: {} }), null)
  assert.equal(extractText({ conversation: 'a'.repeat(5001) }), null)
})
test('bridge cannot call remote endpoint or use short secrets', () => {
  const key = 'a'.repeat(32)
  assert.equal(validConfig('http://127.0.0.1:8000', key), true)
  assert.equal(validConfig('https://example.com', key), false)
  assert.equal(validConfig('http://evil.example', key), false)
  assert.equal(validConfig('http://127.0.0.1:8000', 'short'), false)
})
