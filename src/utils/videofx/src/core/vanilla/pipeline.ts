import { buildWebGL2Pipeline } from '../../pipelines/webgl2/webgl2Pipeline';
import { BackgroundConfig } from '../helpers/backgroundHelper';
import { SegmentationConfig } from '../helpers/segmentationHelper';
import { TFLite } from './TFLite';

function pipeline(
  video: HTMLVideoElement,
  canvasOutput: HTMLCanvasElement,
  backgroundConfig: BackgroundConfig,
  segmentationConfig: SegmentationConfig,
  tflite: TFLite
) {
  let fps: number = 0;
  let durations: number[] = [];
  let previousTime: number = 0
  let beginTime: number = 0
  let eventCount: number = 0
  let frameCount: number = 0
  const frameDurations: number[] = []

  // let interval: any;
  let renderRequestId: number

  let webglPipeline = buildWebGL2Pipeline(
    video,
    backgroundConfig,
    segmentationConfig,
    canvasOutput,
    tflite,
    addFrameEvent
  );

  webglPipeline.updatePostProcessingConfig({
    smoothSegmentationMask: true,
    jointBilateralFilter: { sigmaSpace: 1, sigmaColor: 0.1 },
    coverage: [0.5, 0.75],
    lightWrapping: 0.3,
    blendMode: 'screen',
  })

  async function render() {
    beginFrame()
    try {
      await webglPipeline.render()
      endFrame()
      renderRequestId = requestAnimationFrame(render)
    } catch (error) {
      if (renderRequestId) cancelAnimationFrame(renderRequestId)
      webglPipeline.cleanUp()
      throw error
    }
  }

  function beginFrame() {
    beginTime = Date.now()
  }

  function addFrameEvent() {
    const time = Date.now()
    frameDurations[eventCount] = time - beginTime
    beginTime = time
    eventCount++
  }

  function endFrame() {
    const time = Date.now()
    frameDurations[eventCount] = time - beginTime
    frameCount++
    if (time >= previousTime + 1000) {
      fps = (frameCount * 1000) / (time - previousTime)
      durations = frameDurations;
      previousTime = time
      frameCount = 0
    }
    eventCount = 0
  }

  render()
  // interval = setInterval(() => {
  //   renderRequestId = requestAnimationFrame(render)
  // }, 1000 / 30)

  return {
    webglPipeline,
    canvasOutput,
    fps,
    durations,
  }
}

export default pipeline;
