/**
 * Use it along with boyswan.glsl-literal VSCode extension
 * to get GLSL syntax highlighting.
 * https://marketplace.visualstudio.com/items?itemName=boyswan.glsl-literal
 *
 * On VSCode OSS, boyswan.glsl-literal requires slevesque.shader extension
 * to be installed as well.
 * https://marketplace.visualstudio.com/items?itemName=slevesque.shader
 */
export const glsl = String.raw

export function createPiplelineStageProgram(
  gl: WebGL2RenderingContext,
  vertexShader: WebGLShader,
  fragmentShader: WebGLShader,
  positionBuffer: WebGLBuffer,
  texCoordBuffer: WebGLBuffer
) {
  const program = createProgram(gl, vertexShader, fragmentShader)

  const positionAttributeLocation = gl.getAttribLocation(program, 'a_position')
  gl.enableVertexAttribArray(positionAttributeLocation)
  gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer)
  gl.vertexAttribPointer(positionAttributeLocation, 2, gl.FLOAT, false, 0, 0)

  const texCoordAttributeLocation = gl.getAttribLocation(program, 'a_texCoord')
  gl.enableVertexAttribArray(texCoordAttributeLocation)
  gl.bindBuffer(gl.ARRAY_BUFFER, texCoordBuffer)
  gl.vertexAttribPointer(texCoordAttributeLocation, 2, gl.FLOAT, false, 0, 0)

  return program
}

export function createProgram(
  gl: WebGL2RenderingContext,
  vertexShader: WebGLShader,
  fragmentShader: WebGLShader
) {
  const program = gl.createProgram()!
  gl.attachShader(program, vertexShader)
  gl.attachShader(program, fragmentShader)
  gl.linkProgram(program)
  if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
    throw new Error(
      `Could not link WebGL program: ${gl.getProgramInfoLog(program)}`
    )
  }
  return program
}

export function compileShader(
  gl: WebGL2RenderingContext,
  shaderType: number,
  shaderSource: string
) {
  const shader = gl.createShader(shaderType)!
  gl.shaderSource(shader, shaderSource)
  gl.compileShader(shader)
  if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
    throw new Error(`Could not compile shader: ${gl.getShaderInfoLog(shader)}`)
  }
  return shader
}

export function createTexture(
  gl: WebGL2RenderingContext,
  internalformat: number,
  width: number,
  height: number,
  minFilter = gl.NEAREST,
  magFilter = gl.NEAREST
) {
  const texture = gl.createTexture()
  gl.bindTexture(gl.TEXTURE_2D, texture)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, minFilter)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, magFilter)
  gl.texStorage2D(gl.TEXTURE_2D, 1, internalformat, width, height)
  return texture
}

export async function readPixelsAsync(
  gl: WebGL2RenderingContext,
  x: number,
  y: number,
  width: number,
  height: number,
  format: number,
  type: number,
  dest: ArrayBufferView
) {
  const buf = gl.createBuffer()!
  gl.bindBuffer(gl.PIXEL_PACK_BUFFER, buf)
  gl.bufferData(gl.PIXEL_PACK_BUFFER, dest.byteLength, gl.STREAM_READ)
  gl.readPixels(x, y, width, height, format, type, 0)
  gl.bindBuffer(gl.PIXEL_PACK_BUFFER, null)

  await getBufferSubDataAsync(gl, gl.PIXEL_PACK_BUFFER, buf, 0, dest)

  gl.deleteBuffer(buf)
  return dest
}

async function getBufferSubDataAsync(
  gl: WebGL2RenderingContext,
  target: number,
  buffer: WebGLBuffer,
  srcByteOffset: number,
  dstBuffer: ArrayBufferView,
  dstOffset?: number,
  length?: number
) {
  const sync = gl.fenceSync(gl.SYNC_GPU_COMMANDS_COMPLETE, 0)!
  gl.flush()
  const res = await clientWaitAsync(gl, sync)
  gl.deleteSync(sync)

  if (res !== gl.WAIT_FAILED) {
    gl.bindBuffer(target, buffer)
    gl.getBufferSubData(target, srcByteOffset, dstBuffer, dstOffset, length)
    gl.bindBuffer(target, null)
  }
}

function clientWaitAsync(gl: WebGL2RenderingContext, sync: WebGLSync) {
  return new Promise<number>((resolve) => {
    function test() {
      const res = gl.clientWaitSync(sync, 0, 0)
      if (res === gl.WAIT_FAILED) {
        resolve(res)
        return
      }
      if (res === gl.TIMEOUT_EXPIRED) {
        requestAnimationFrame(test)
        return
      }
      resolve(res)
    }
    requestAnimationFrame(test)
  })
}
