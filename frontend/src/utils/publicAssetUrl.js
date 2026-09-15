export function publicAssetUrl(path) {
  const baseUrl = (import.meta.env.BASE_URL || '/').replace(/\/*$/, '/')

  return `${baseUrl}${String(path).replace(/^\/+/, '')}`
}
