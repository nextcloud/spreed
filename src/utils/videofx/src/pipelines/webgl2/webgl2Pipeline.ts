import { BackgroundConfig } from '../../core/helpers/backgroundHelper'
import { PostProcessingConfig } from '../../core/helpers/postProcessingHelper'
import {
  inputResolutions,
  SegmentationConfig
} from '../../core/helpers/segmentationHelper'
import { TFLite } from '../../core/vanilla/TFLite'
import { compileShader, createTexture, glsl } from '../helpers/webglHelper'
import {
  BackgroundBlurStage,
  buildBackgroundBlurStage
} from './backgroundBlurStage'
import { buildJointBilateralFilterStage } from './jointBilateralFilterStage'
import { buildResizingStage } from './resizingStage'
import { buildSoftmaxStage } from './softmaxStage'

export function buildWebGL2Pipeline(
  video: HTMLVideoElement,
  backgroundConfig: BackgroundConfig,
  segmentationConfig: SegmentationConfig,
  canvas: HTMLCanvasElement,
  tflite: TFLite,
  addFrameEvent: () => void
) {
  const vertexShaderSource = glsl`#version 300 es

    in vec2 a_position;
    in vec2 a_texCoord;

    out vec2 v_texCoord;

    void main() {
      gl_Position = vec4(a_position, 0.0, 1.0);
      v_texCoord = a_texCoord;
    }
  `

  const { videoWidth: width, videoHeight: height } = video;
  const frameWidth: number = width ?? 0;
  const frameHeight: number = height ?? 0;
  const [segmentationWidth, segmentationHeight] = inputResolutions[
    segmentationConfig.inputResolution
  ]

  const gl = canvas.getContext('webgl2')!

  const vertexShader = compileShader(gl, gl.VERTEX_SHADER, vertexShaderSource)

  const vertexArray = gl.createVertexArray()
  gl.bindVertexArray(vertexArray)

  const positionBuffer = gl.createBuffer()!
  gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer)
  gl.bufferData(
    gl.ARRAY_BUFFER,
    new Float32Array([-1.0, -1.0, 1.0, -1.0, -1.0, 1.0, 1.0, 1.0]),
    gl.STATIC_DRAW
  )

  const texCoordBuffer = gl.createBuffer()!
  gl.bindBuffer(gl.ARRAY_BUFFER, texCoordBuffer)
  gl.bufferData(
    gl.ARRAY_BUFFER,
    new Float32Array([0.0, 0.0, 1.0, 0.0, 0.0, 1.0, 1.0, 1.0]),
    gl.STATIC_DRAW
  )

  // We don't use texStorage2D here because texImage2D seems faster
  // to upload video texture than texSubImage2D even though the latter
  // is supposed to be the recommended way:
  // https://developer.mozilla.org/en-US/docs/Web/API/WebGL_API/WebGL_best_practices#use_texstorage_to_create_textures
  const inputFrameTexture = gl.createTexture()
  gl.bindTexture(gl.TEXTURE_2D, inputFrameTexture)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST)

  // TODO Rename segmentation and person mask to be more specific
  const segmentationTexture = createTexture(
    gl,
    gl.RGBA8,
    segmentationWidth,
    segmentationHeight
  )!
  const personMaskTexture = createTexture(
    gl,
    gl.RGBA8,
    frameWidth,
    frameHeight
  )!

  const resizingStage = buildResizingStage(
    gl,
    vertexShader,
    positionBuffer,
    texCoordBuffer,
    segmentationConfig,
    tflite
  )
  const loadSegmentationStage = buildSoftmaxStage(
    gl,
    vertexShader,
    positionBuffer,
    texCoordBuffer,
    segmentationConfig,
    tflite,
    segmentationTexture
  )
  const jointBilateralFilterStage = buildJointBilateralFilterStage(
    gl,
    vertexShader,
    positionBuffer,
    texCoordBuffer,
    segmentationTexture,
    segmentationConfig,
    personMaskTexture,
    canvas
  )
  const backgroundStage = buildBackgroundBlurStage(
    gl,
    vertexShader,
    positionBuffer,
    texCoordBuffer,
    personMaskTexture,
    canvas
  )

  async function render() {
    gl.clearColor(0, 0, 0, 0)
    gl.clear(gl.COLOR_BUFFER_BIT)

    gl.activeTexture(gl.TEXTURE0)
    gl.bindTexture(gl.TEXTURE_2D, inputFrameTexture)

    // texImage2D seems faster than texSubImage2D to upload
    // video texture
    gl.texImage2D(
      gl.TEXTURE_2D,
      0,
      gl.RGBA,
      gl.RGBA,
      gl.UNSIGNED_BYTE,
      video
    )

    gl.bindVertexArray(vertexArray)

    resizingStage.render()

    addFrameEvent()

    tflite._runInference()

    addFrameEvent()

    loadSegmentationStage.render()
    jointBilateralFilterStage.render()
    backgroundStage.render()
  }

  function updatePostProcessingConfig(
    postProcessingConfig: PostProcessingConfig
  ) {
    jointBilateralFilterStage.updateSigmaSpace(
      postProcessingConfig.jointBilateralFilter.sigmaSpace
    )
    jointBilateralFilterStage.updateSigmaColor(
      postProcessingConfig.jointBilateralFilter.sigmaColor
    )

    const backgroundBlurStage = backgroundStage as BackgroundBlurStage
    backgroundBlurStage.updateCoverage(postProcessingConfig.coverage)
  }

  function cleanUp() {
    backgroundStage.cleanUp()
    jointBilateralFilterStage.cleanUp()
    loadSegmentationStage.cleanUp()
    resizingStage.cleanUp()

    gl.deleteTexture(personMaskTexture)
    gl.deleteTexture(segmentationTexture)
    gl.deleteTexture(inputFrameTexture)
    gl.deleteBuffer(texCoordBuffer)
    gl.deleteBuffer(positionBuffer)
    gl.deleteVertexArray(vertexArray)
    gl.deleteShader(vertexShader)
  }

  return { render, updatePostProcessingConfig, cleanUp }
}
