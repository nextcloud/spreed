export type BackgroundConfig = {
  type: 'blur'
  url?: string
}

export const backgroundImageUrls = [
  'architecture-5082700_1280',
  'porch-691330_1280',
  'saxon-switzerland-539418_1280',
  'shibuyasky-4768679_1280',
].map((imageName) => `${process.env.PUBLIC_URL}/backgrounds/${imageName}.jpg`)
