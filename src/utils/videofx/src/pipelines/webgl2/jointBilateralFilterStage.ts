import {
  inputResolutions,
  SegmentationConfig,
} from '../../core/helpers/segmentationHelper'
import {
  compileShader,
  createPiplelineStageProgram,
  glsl,
} from '../helpers/webglHelper'

export function buildJointBilateralFilterStage(
  gl: WebGL2RenderingContext,
  vertexShader: WebGLShader,
  positionBuffer: WebGLBuffer,
  texCoordBuffer: WebGLBuffer,
  inputTexture: WebGLTexture,
  segmentationConfig: SegmentationConfig,
  outputTexture: WebGLTexture,
  canvas: HTMLCanvasElement
) {
  const fragmentShaderSource = glsl`#version 300 es

    precision highp float;

    uniform sampler2D u_inputFrame;
    uniform sampler2D u_segmentationMask;
    uniform vec2 u_texelSize;
    uniform float u_step;
    uniform float u_radius;
    uniform float u_offset;
    uniform float u_sigmaTexel;
    uniform float u_sigmaColor;

    in vec2 v_texCoord;

    out vec4 outColor;

    float gaussian(float x, float sigma) {
      float coeff = -0.5 / (sigma * sigma * 4.0 + 1.0e-6);
      return exp((x * x) * coeff);
    }

    void main() {
      vec2 centerCoord = v_texCoord;
      vec3 centerColor = texture(u_inputFrame, centerCoord).rgb;
      float newVal = 0.0;

      float spaceWeight = 0.0;
      float colorWeight = 0.0;
      float totalWeight = 0.0;

      // Subsample kernel space.
      for (float i = -u_radius + u_offset; i <= u_radius; i += u_step) {
        for (float j = -u_radius + u_offset; j <= u_radius; j += u_step) {
          vec2 shift = vec2(j, i) * u_texelSize;
          vec2 coord = vec2(centerCoord + shift);
          vec3 frameColor = texture(u_inputFrame, coord).rgb;
          float outVal = texture(u_segmentationMask, coord).a;

          spaceWeight = gaussian(distance(centerCoord, coord), u_sigmaTexel);
          colorWeight = gaussian(distance(centerColor, frameColor), u_sigmaColor);
          totalWeight += spaceWeight * colorWeight;

          newVal += spaceWeight * colorWeight * outVal;
        }
      }
      newVal /= totalWeight;

      outColor = vec4(vec3(0.0), newVal);
    }
  `

  const [segmentationWidth, segmentationHeight] = inputResolutions[
    segmentationConfig.inputResolution
  ]
  const { width: outputWidth, height: outputHeight } = canvas
  const texelWidth = 1 / outputWidth
  const texelHeight = 1 / outputHeight

  const fragmentShader = compileShader(
    gl,
    gl.FRAGMENT_SHADER,
    fragmentShaderSource
  )
  const program = createPiplelineStageProgram(
    gl,
    vertexShader,
    fragmentShader,
    positionBuffer,
    texCoordBuffer
  )
  const inputFrameLocation = gl.getUniformLocation(program, 'u_inputFrame')
  const segmentationMaskLocation = gl.getUniformLocation(
    program,
    'u_segmentationMask'
  )
  const texelSizeLocation = gl.getUniformLocation(program, 'u_texelSize')
  const stepLocation = gl.getUniformLocation(program, 'u_step')
  const radiusLocation = gl.getUniformLocation(program, 'u_radius')
  const offsetLocation = gl.getUniformLocation(program, 'u_offset')
  const sigmaTexelLocation = gl.getUniformLocation(program, 'u_sigmaTexel')
  const sigmaColorLocation = gl.getUniformLocation(program, 'u_sigmaColor')

  const frameBuffer = gl.createFramebuffer()
  gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer)
  gl.framebufferTexture2D(
    gl.FRAMEBUFFER,
    gl.COLOR_ATTACHMENT0,
    gl.TEXTURE_2D,
    outputTexture,
    0
  )

  gl.useProgram(program)
  gl.uniform1i(inputFrameLocation, 0)
  gl.uniform1i(segmentationMaskLocation, 1)
  gl.uniform2f(texelSizeLocation, texelWidth, texelHeight)

  // Ensures default values are configured to prevent infinite
  // loop in fragment shader
  updateSigmaSpace(0)
  updateSigmaColor(0)

  function render() {
    gl.viewport(0, 0, outputWidth, outputHeight)
    gl.useProgram(program)
    gl.activeTexture(gl.TEXTURE1)
    gl.bindTexture(gl.TEXTURE_2D, inputTexture)
    gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer)
    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4)
  }

  function updateSigmaSpace(sigmaSpace: number) {
    sigmaSpace *= Math.max(
      outputWidth / segmentationWidth,
      outputHeight / segmentationHeight
    )

    const kSparsityFactor = 0.66 // Higher is more sparse.
    const sparsity = Math.max(1, Math.sqrt(sigmaSpace) * kSparsityFactor)
    const step = sparsity
    const radius = sigmaSpace
    const offset = step > 1 ? step * 0.5 : 0
    const sigmaTexel = Math.max(texelWidth, texelHeight) * sigmaSpace

    gl.useProgram(program)
    gl.uniform1f(stepLocation, step)
    gl.uniform1f(radiusLocation, radius)
    gl.uniform1f(offsetLocation, offset)
    gl.uniform1f(sigmaTexelLocation, sigmaTexel)
  }

  function updateSigmaColor(sigmaColor: number) {
    gl.useProgram(program)
    gl.uniform1f(sigmaColorLocation, sigmaColor)
  }

  function cleanUp() {
    gl.deleteFramebuffer(frameBuffer)
    gl.deleteProgram(program)
    gl.deleteShader(fragmentShader)
  }

  return { render, updateSigmaSpace, updateSigmaColor, cleanUp }
}
