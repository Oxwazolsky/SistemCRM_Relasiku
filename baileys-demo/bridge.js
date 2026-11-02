export function phoneFromJid(jid) {
  if (typeof jid !== 'string' || !/^\d{8,15}@s\.whatsapp\.net$/.test(jid)) return null
  return jid.split('@')[0]
}

export async function senderPhone(key, lidMapping) {
  // Only direct chats: never accept group participants or status broadcasts.
  if (!key || key.fromMe || !/^\d{8,20}@(s\.whatsapp\.net|lid)$/.test(key.remoteJid ?? '')) return null
  const direct = phoneFromJid(key.remoteJid)
  if (direct) return direct
  const alternate = phoneFromJid(key.remoteJidAlt)
  if (alternate) return alternate
  if (!lidMapping?.getPNForLID) return null
  return phoneFromJid(await lidMapping.getPNForLID(key.remoteJid))
}

export function extractText(message) {
  let content = message
  for (let i = 0; i < 3; i++) {
    if (content?.ephemeralMessage?.message) content = content.ephemeralMessage.message
    else if (content?.viewOnceMessage?.message) content = content.viewOnceMessage.message
    else break
  }
  const text = content?.conversation ?? content?.extendedTextMessage?.text
  return typeof text === 'string' && text.trim().length && text.length <= 5000 ? text.trim() : null
}

export function validConfig(url, secret) {
  try {
    const parsed = new URL(url)
    return parsed.protocol === 'http:' && ['127.0.0.1', 'localhost', '[::1]'].includes(parsed.hostname)
      && typeof secret === 'string' && secret.length >= 32
  } catch { return false }
}
