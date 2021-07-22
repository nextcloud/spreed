export type SegmentationModel = 'meet'
export type SegmentationBackend = 'webgl' | 'wasm' | 'wasmSimd'
export type InputResolution = '256x144' | '160x96'

export const inputResolutions: {
  [resolution in InputResolution]: [number, number]
} = {
  '256x144': [256, 144],
  '160x96': [160, 96],
}

export type PipelineName = 'webgl2'

export type SegmentationConfig = {
  model: SegmentationModel
  backend: SegmentationBackend
  inputResolution: InputResolution
  pipeline: PipelineName
}

export function getTFLiteModelFileName(
  model: SegmentationModel,
  inputResolution: InputResolution
) {
  switch (model) {
    case 'meet':
      return inputResolution === '256x144' ? 'segm_full_v679' : 'segm_lite_v681'

    default:
      throw new Error(`No TFLite file for this segmentation model: ${model}`)
  }
}
