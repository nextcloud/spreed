import { BackgroundConfig } from '../helpers/backgroundHelper';
import { SegmentationConfig } from '../helpers/segmentationHelper';
import pipeline from './pipeline';
import { getTFLite, TFLite } from './TFLite';

export const blur = async (video: HTMLVideoElement, canvas: HTMLCanvasElement) => {
  const { tflite } = await getTFLite();
  const backgroundConfig: BackgroundConfig = { type: 'blur' };
  const segmentationConfig: SegmentationConfig = {
    model: 'meet',
    backend: 'wasm',
    inputResolution: '160x96',
    // inputResolution: '256x144', // consider using this and the larger model when SIMD is available
    pipeline: 'webgl2'
  };
  const { webglPipeline } = pipeline(video, canvas, backgroundConfig, segmentationConfig, tflite as TFLite);
  webglPipeline.render();
}
