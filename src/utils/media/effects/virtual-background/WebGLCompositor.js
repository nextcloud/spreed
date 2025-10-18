/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// @flow

/**
 * WebGL-based compositor for background effects.
 * Incorporates joint bilateral filtering and multi-pass blur for improved quality.
 *
 * @class
 */
export default class WebGLCompositor {
	/**
	 * Create a new WebGL compositor bound to a canvas.
	 *
	 * @param {HTMLCanvasElement} canvas - Canvas element to render into.
	 * @throws {Error} If WebGL is not available.
	 */
	constructor(canvas) {
		this.canvas = canvas
		this.gl = canvas.getContext('webgl2', { premultipliedAlpha: false, alpha: true })
		if (!this.gl) {
			throw new Error('WebGL2 not available')
		}

		const gl = this.gl

		// --- Compile Helpers ---
		this._compileShader = (gl, type, src) => {
			const s = gl.createShader(type)
			gl.shaderSource(s, src)
			gl.compileShader(s)
			if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
				throw new Error(gl.getShaderInfoLog(s))
			}
			return s
		}

		this._linkProgram = (gl, vsSrc, fsSrc) => {
			const prog = gl.createProgram()
			gl.attachShader(prog, this._compileShader(gl, gl.VERTEX_SHADER, vsSrc))
			gl.attachShader(prog, this._compileShader(gl, gl.FRAGMENT_SHADER, fsSrc))
			gl.linkProgram(prog)
			if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
				throw new Error(gl.getProgramInfoLog(prog))
			}
			return prog
		}

		// --- Main Vertex Shader ---
		const vs = `#version 300 es
		in vec2 a_pos;
		in vec2 a_texCoord;
		out vec2 v_texCoord;
		void main() {
			gl_Position = vec4(a_pos, 0.0, 1.0);
			v_texCoord = a_texCoord;
		}`

		// --- Vertex shader for final output (flips Y) ---
		const vsOutput = `#version 300 es
		in vec2 a_pos;
		in vec2 a_texCoord;
		out vec2 v_texCoord;
		void main() {
			// Flipping Y is required when rendering to canvas
			gl_Position = vec4(a_pos * vec2(1.0, -1.0), 0.0, 1.0);
			v_texCoord = a_texCoord;
		}`

		// --- Joint Bilateral Filter Fragment Shader ---
		const bilateralFS = `#version 300 es
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

			// Subsample kernel space
			for (float i = -u_radius + u_offset; i <= u_radius; i += u_step) {
				for (float j = -u_radius + u_offset; j <= u_radius; j += u_step) {
					vec2 shift = vec2(j, i) * u_texelSize;
					vec2 coord = centerCoord + shift;
					vec3 frameColor = texture(u_inputFrame, coord).rgb;
					float outVal = texture(u_segmentationMask, coord).r;

					spaceWeight = gaussian(distance(centerCoord, coord), u_sigmaTexel);
					colorWeight = gaussian(distance(centerColor, frameColor), u_sigmaColor);
					totalWeight += spaceWeight * colorWeight;

					newVal += spaceWeight * colorWeight * outVal;
				}
			}
			newVal /= totalWeight;

			outColor = vec4(vec3(0.0), newVal);
		}`

		// --- Gaussian Blur Fragment Shader ---
		const blurFS = `#version 300 es
		precision highp float;

		uniform sampler2D u_inputFrame;
		uniform sampler2D u_personMask;
		uniform vec2 u_texelSize;

		in vec2 v_texCoord;
		out vec4 outColor;

		const float offset[5] = float[](0.0, 1.0, 2.0, 3.0, 4.0);
		const float weight[5] = float[](0.2270270270, 0.1945945946, 0.1216216216,
			0.0540540541, 0.0162162162);

		void main() {
			vec4 centerColor = texture(u_inputFrame, v_texCoord);
			float personMask = texture(u_personMask, v_texCoord).a;

			vec4 frameColor = centerColor * weight[0] * (1.0 - personMask);

			for (int i = 1; i < 5; i++) {
				vec2 offsetVec = vec2(offset[i]) * u_texelSize;

				vec2 texCoord = v_texCoord + offsetVec;
				frameColor += texture(u_inputFrame, texCoord) * weight[i] *
					(1.0 - texture(u_personMask, texCoord).a);

				texCoord = v_texCoord - offsetVec;
				frameColor += texture(u_inputFrame, texCoord) * weight[i] *
					(1.0 - texture(u_personMask, texCoord).a);
			}
			outColor = vec4(frameColor.rgb + (1.0 - frameColor.a) * centerColor.rgb, 1.0);
		}`

		// --- HUD (solid-color) program for translucent overlays ---
		const hudVS = `#version 300 es
    	in vec2 a_pos;
    	void main() {
    		gl_Position = vec4(a_pos, 0.0, 1.0);
    	}`

		const hudFS = `#version 300 es
    	precision highp float;
    	uniform vec4 u_color;
    	out vec4 outColor;
    	void main() {
    		outColor = u_color;
    	}`

		// --- Final Blend Fragment Shader ---
		const blendFS = `#version 300 es
		precision highp float;

		uniform sampler2D u_inputFrame;
		uniform sampler2D u_personMask;
		uniform sampler2D u_blurredFrame;
		uniform sampler2D u_background;
		uniform vec2 u_coverage;
		uniform float u_lightWrapping;
		uniform int u_mode;

		in vec2 v_texCoord;
		out vec4 outColor;

		vec3 screen(vec3 a, vec3 b) {
			return 1.0 - (1.0 - a) * (1.0 - b);
		}

		vec3 linearDodge(vec3 a, vec3 b) {
			return a + b;
		}

		void main() {
			vec3 frameColor = texture(u_inputFrame, v_texCoord).rgb;
			float personMask = texture(u_personMask, v_texCoord).a;

			vec3 bgColor;
			if (u_mode == 1) {
				// Blur mode
				bgColor = texture(u_blurredFrame, v_texCoord).rgb;
			} else {
				// Background image mode
				vec2 bgCoord = v_texCoord;
				bgColor = texture(u_background, bgCoord).rgb;

				// Apply light wrapping
				float lightWrapMask = 1.0 - max(0.0, personMask - u_coverage.y) / (1.0 - u_coverage.y);
				vec3 lightWrap = u_lightWrapping * lightWrapMask * bgColor;
				frameColor = screen(frameColor, lightWrap);
			}

			// Apply coverage smoothing
			personMask = smoothstep(u_coverage.x, u_coverage.y, personMask);

			outColor = vec4(mix(bgColor, frameColor, personMask), 1.0);
		}`

		// --- Link Programs ---
		this.progBilateral = this._linkProgram(gl, vs, bilateralFS)
		this.progBlur = this._linkProgram(gl, vs, blurFS)
		this.progBlend = this._linkProgram(gl, vsOutput, blendFS)
		this.progHUD = this._linkProgram(gl, hudVS, hudFS)

		// --- Setup vertex buffers ---
		this.vertexArray = gl.createVertexArray()
		gl.bindVertexArray(this.vertexArray)

		this.positionBuffer = gl.createBuffer()
		gl.bindBuffer(gl.ARRAY_BUFFER, this.positionBuffer)
		gl.bufferData(
			gl.ARRAY_BUFFER,
			new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]),
			gl.STATIC_DRAW,
		)

		this.texCoordBuffer = gl.createBuffer()
		gl.bindBuffer(gl.ARRAY_BUFFER, this.texCoordBuffer)
		gl.bufferData(
			gl.ARRAY_BUFFER,
			new Float32Array([0, 0, 1, 0, 0, 1, 1, 1]),
			gl.STATIC_DRAW,
		)

		// --- Textures ---
		this.texFrame = this._makeTex()
		this.texMask = this._makeTex()
		this.texMaskFiltered = this._makeTex()
		this.texBg = this._makeTex()
		this.texBlurred1 = this._makeTex()
		this.texBlurred2 = this._makeTex()

		// --- Framebuffers ---
		this.fboMask = gl.createFramebuffer()
		this.fboBlur1 = gl.createFramebuffer()
		this.fboBlur2 = gl.createFramebuffer()

		// --- Blit variables, lazy loaded ---
		this.progBlit = null
		this.blitBuf = null
		this.blitPosLoc = null
		this.blitSamplerLoc = null

		// --- Default parameters ---
		this.sigmaSpace = 10.0
		this.sigmaColor = 0.15
		this.coverage = [0.45, 0.75]
		this.lightWrapping = 0.3
		this.progressBarColor = [0, 0.4, 0.62, 1] // Nextcloud default primary color (#00679E)
	}

	/**
	 * Create and initialize a WebGL texture.
	 *
	 * @private
	 * @return {WebGLTexture} Newly created texture.
	 */
	_makeTex() {
		const gl = this.gl
		const t = gl.createTexture()
		gl.bindTexture(gl.TEXTURE_2D, t)
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR)
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR)
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE)
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE)
		return t
	}

	/**
	 * Upload an image/video/canvas frame into a WebGL texture.
	 *
	 * @private
	 * @param {WebGLTexture} tex - Texture to upload into.
	 * @param {HTMLImageElement|HTMLVideoElement|HTMLCanvasElement} source - Source element.
	 * @param {object} [options] - Upload options.
	 * @param {boolean} [options.flipY] - Whether to flip vertically.
	 * @param {number} [options.min] - Minification filter.
	 * @param {number} [options.mag] - Magnification filter.
	 * @return {void}
	 */
	_upload(tex, source, options = {}) {
		const gl = this.gl
		if (!source) {
			return
		}

		// Validation
		if (source instanceof HTMLImageElement) {
			if (!source.complete || source.naturalWidth === 0) {
				return
			}
		}
		if (source instanceof HTMLVideoElement) {
			if (source.videoWidth === 0 || source.videoHeight === 0) {
				return
			}
		}
		if (source instanceof HTMLCanvasElement) {
			if (source.width === 0 || source.height === 0) {
				return
			}
		}

		// Default to flipping Y, but allow it to be overridden
		const flipY = options.flipY !== undefined ? options.flipY : false

		gl.bindTexture(gl.TEXTURE_2D, tex)
		gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, flipY)

		// Allow custom texture parameters to be set
		if (options.min) {
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, options.min)
		}
		if (options.mag) {
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, options.mag)
		}

		gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, source)
	}

	/**
	 * Initialize shaders and buffers for blitting textures.
	 *
	 * @private
	 * @param {WebGLRenderingContext} gl - GL context.
	 * @return {void}
	 */
	_initBlitResources(gl) {
		if (this.progBlit) {
			return
		}

		const blitVS = `
		attribute vec2 a_pos;
		varying vec2 v_uv;
		void main() {
		  v_uv = (a_pos + 1.0) * 0.5;
		  gl_Position = vec4(a_pos, 0.0, 1.0);
		}`
		const blitFS = `
		precision mediump float;
		varying vec2 v_uv;
		uniform sampler2D u_tex;
		void main() {
		  gl_FragColor = texture2D(u_tex, v_uv);
		}`

		this.progBlit = this._linkProgram(gl, blitVS, blitFS)

		this.blitBuf = gl.createBuffer()
		gl.bindBuffer(gl.ARRAY_BUFFER, this.blitBuf)
		gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, -1, 1, 1, -1, 1]), gl.STATIC_DRAW)

		this.blitPosLoc = gl.getAttribLocation(this.progBlit, 'a_pos')
		this.blitSamplerLoc = gl.getUniformLocation(this.progBlit, 'u_tex')
	}

	/**
	 * Copy a MediaPipe mask texture into a canvas.
	 *
	 * @private
	 * @param {object} mask - MediaPipe mask object with canvas + getAsWebGLTexture().
	 * @return {void}
	 */
	_blitTextureToCanvas(mask) {
		const gl = mask.canvas.getContext('webgl2')
		if (!gl) {
			console.error('Could not get WebGL context from mask canvas.')
			return
		}
		this._initBlitResources(gl)

		const texture = mask.getAsWebGLTexture()
		const { width, height } = mask

		gl.useProgram(this.progBlit)

		gl.bindBuffer(gl.ARRAY_BUFFER, this.blitBuf)
		gl.enableVertexAttribArray(this.blitPosLoc)
		gl.vertexAttribPointer(this.blitPosLoc, 2, gl.FLOAT, false, 0, 0)

		gl.activeTexture(gl.TEXTURE0)
		gl.bindTexture(gl.TEXTURE_2D, texture)
		gl.uniform1i(this.blitSamplerLoc, 0)

		gl.bindFramebuffer(gl.FRAMEBUFFER, null)
		gl.viewport(0, 0, width, height)
		gl.clearColor(0, 0, 0, 0)
		gl.clear(gl.COLOR_BUFFER_BIT)
		gl.drawArrays(gl.TRIANGLES, 0, 6)
	}

	/**
	 * Setup vertex attributes for rendering.
	 *
	 * @private
	 * @param {WebGLProgram} prog - Shader program.
	 * @return {void}
	 */
	_setupVertexAttributes(prog) {
		const gl = this.gl

		const posLoc = gl.getAttribLocation(prog, 'a_pos')
		if (posLoc !== -1) {
			gl.bindBuffer(gl.ARRAY_BUFFER, this.positionBuffer)
			gl.enableVertexAttribArray(posLoc)
			gl.vertexAttribPointer(posLoc, 2, gl.FLOAT, false, 0, 0)
		}

		const texLoc = gl.getAttribLocation(prog, 'a_texCoord')
		if (texLoc !== -1) {
			gl.bindBuffer(gl.ARRAY_BUFFER, this.texCoordBuffer)
			gl.enableVertexAttribArray(texLoc)
			gl.vertexAttribPointer(texLoc, 2, gl.FLOAT, false, 0, 0)
		}
	}

	/**
	 * Apply joint bilateral filter to mask.
	 *
	 * @private
	 * @param {number} width - Output width.
	 * @param {number} height - Output height.
	 * @return {void}
	 */
	_applyBilateralFilter(width, height) {
		const gl = this.gl

		// Bind filtered mask FBO
		gl.bindFramebuffer(gl.FRAMEBUFFER, this.fboMask)
		gl.framebufferTexture2D(
			gl.FRAMEBUFFER,
			gl.COLOR_ATTACHMENT0,
			gl.TEXTURE_2D,
			this.texMaskFiltered,
			0,
		)

		gl.viewport(0, 0, width, height)
		gl.useProgram(this.progBilateral)
		this._setupVertexAttributes(this.progBilateral)

		// Calculate filter parameters
		const texelWidth = 1 / width
		const texelHeight = 1 / height
		const kSparsityFactor = 0.66
		const step = Math.max(1, Math.sqrt(this.sigmaSpace) * kSparsityFactor)
		const radius = this.sigmaSpace
		const offset = step > 1 ? step * 0.5 : 0
		const sigmaTexel = Math.max(texelWidth, texelHeight) * this.sigmaSpace

		// Set uniforms
		gl.uniform1i(gl.getUniformLocation(this.progBilateral, 'u_inputFrame'), 0)
		gl.uniform1i(gl.getUniformLocation(this.progBilateral, 'u_segmentationMask'), 1)
		gl.uniform2f(gl.getUniformLocation(this.progBilateral, 'u_texelSize'), texelWidth, texelHeight)
		gl.uniform1f(gl.getUniformLocation(this.progBilateral, 'u_step'), step)
		gl.uniform1f(gl.getUniformLocation(this.progBilateral, 'u_radius'), radius)
		gl.uniform1f(gl.getUniformLocation(this.progBilateral, 'u_offset'), offset)
		gl.uniform1f(gl.getUniformLocation(this.progBilateral, 'u_sigmaTexel'), sigmaTexel)
		gl.uniform1f(gl.getUniformLocation(this.progBilateral, 'u_sigmaColor'), this.sigmaColor)

		// Bind textures
		gl.activeTexture(gl.TEXTURE0)
		gl.bindTexture(gl.TEXTURE_2D, this.texFrame)
		gl.activeTexture(gl.TEXTURE1)
		gl.bindTexture(gl.TEXTURE_2D, this.texMask)

		gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4)
	}

	/**
	 * Apply multi-pass Gaussian blur.
	 *
	 * @private
	 * @param {number} width - Output width.
	 * @param {number} height - Output height.
	 * @return {void}
	 */
	_applyMultiPassBlur(width, height) {
		const gl = this.gl
		const scale = 0.5
		const blurWidth = width * scale
		const blurHeight = height * scale
		const texelWidth = 1 / blurWidth
		const texelHeight = 1 / blurHeight

		// Allocate blur textures
		gl.bindTexture(gl.TEXTURE_2D, this.texBlurred1)
		gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, blurWidth, blurHeight, 0, gl.RGBA, gl.UNSIGNED_BYTE, null)
		gl.bindTexture(gl.TEXTURE_2D, this.texBlurred2)
		gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, blurWidth, blurHeight, 0, gl.RGBA, gl.UNSIGNED_BYTE, null)

		// Setup FBOs
		gl.bindFramebuffer(gl.FRAMEBUFFER, this.fboBlur1)
		gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, this.texBlurred1, 0)
		gl.bindFramebuffer(gl.FRAMEBUFFER, this.fboBlur2)
		gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, this.texBlurred2, 0)

		gl.viewport(0, 0, blurWidth, blurHeight)
		gl.useProgram(this.progBlur)
		this._setupVertexAttributes(this.progBlur)

		// Set static uniforms
		gl.uniform1i(gl.getUniformLocation(this.progBlur, 'u_inputFrame'), 0)
		gl.uniform1i(gl.getUniformLocation(this.progBlur, 'u_personMask'), 1)

		gl.activeTexture(gl.TEXTURE1)
		gl.bindTexture(gl.TEXTURE_2D, this.texMaskFiltered)

		// Apply 3 blur passes
		for (let i = 0; i < 3; i++) {
			// Horizontal pass
			gl.uniform2f(gl.getUniformLocation(this.progBlur, 'u_texelSize'), 0, texelHeight)
			gl.bindFramebuffer(gl.FRAMEBUFFER, this.fboBlur1)

			if (i === 0) {
				gl.activeTexture(gl.TEXTURE0)
				gl.bindTexture(gl.TEXTURE_2D, this.texFrame)
			} else {
				gl.activeTexture(gl.TEXTURE0)
				gl.bindTexture(gl.TEXTURE_2D, this.texBlurred2)
			}

			gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4)

			// Vertical pass
			gl.uniform2f(gl.getUniformLocation(this.progBlur, 'u_texelSize'), texelWidth, 0)
			gl.bindFramebuffer(gl.FRAMEBUFFER, this.fboBlur2)
			gl.activeTexture(gl.TEXTURE0)
			gl.bindTexture(gl.TEXTURE_2D, this.texBlurred1)
			gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4)
		}
	}

	/**
	 * Draw a solid bottom progress bar using scissor clear (single draw, zero shaders).
	 *
	 * @param {number} outW
	 * @param {number} outH
	 */
	_drawProgressBar(outW, outH) {
		const gl = this.gl
		const w = Math.max(16, Math.floor(0.25 * outW))
		const h = Math.min(8, Math.floor(0.05 * outH))

		const baseSpeed = Math.max(120, Math.floor(outW * 0.9))
		const positionRaw = (baseSpeed * performance.now() / 1000) % (outW + w)
		const x = Math.floor(outW - positionRaw)

		gl.enable(gl.SCISSOR_TEST)

		gl.useProgram(this.progHUD)
		this._setupVertexAttributes(this.progHUD)
		const uColor = gl.getUniformLocation(this.progHUD, 'u_color')
		gl.uniform4f(
			uColor,
			this.progressBarColor[0],
			this.progressBarColor[1],
			this.progressBarColor[2],
			this.progressBarColor[3],
		)

		gl.scissor(x, 0, w, h)
		gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4)

		gl.disable(gl.SCISSOR_TEST)
	}

	/**
	 * Render the source video as is, without applying any kind of effects
	 *
	 * @param {number} outW - Output width.
	 * @param {number} outH - Output height.
	 */
	_renderWithoutEffects(outW, outH) {
		const gl = this.gl

		gl.bindFramebuffer(gl.FRAMEBUFFER, null)
		gl.viewport(0, 0, outW, outH)
		gl.clearColor(0, 0, 0, 1)
		gl.clear(gl.COLOR_BUFFER_BIT)

		// Reuse the blend program but disable mask/background sampling
		gl.useProgram(this.progBlend)
		this._setupVertexAttributes(this.progBlend)

		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_inputFrame'), 0)
		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_personMask'), 1)
		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_blurredFrame'), 2)
		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_background'), 3)
		gl.uniform2f(gl.getUniformLocation(this.progBlend, 'u_coverage'), 0.0, 0.0)
		gl.uniform1f(gl.getUniformLocation(this.progBlend, 'u_lightWrapping'), 0.0)
		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_mode'), -1)

		gl.activeTexture(gl.TEXTURE0)
		gl.bindTexture(gl.TEXTURE_2D, this.texFrame)
		gl.activeTexture(gl.TEXTURE1)
		gl.bindTexture(gl.TEXTURE_2D, null)
		gl.activeTexture(gl.TEXTURE2)
		gl.bindTexture(gl.TEXTURE_2D, null)
		gl.activeTexture(gl.TEXTURE3)
		gl.bindTexture(gl.TEXTURE_2D, null)

		gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4)
	}

	/**
	 * Run the full compositing pipeline.
	 *
	 * @param {object} opts - Rendering options.
	 * @param {HTMLVideoElement} opts.videoEl - Foreground video element.
	 * @param {object} [opts.mask] - Segmentation mask object.
	 * @param {HTMLImageElement|HTMLVideoElement|HTMLCanvasElement} [opts.bgSource] - Background source.
	 * @param {number} opts.mode - Mode (0 = background source, 1 = blur).
	 * @param {number} opts.outW - Output width.
	 * @param {number} opts.outH - Output height.
	 * @param {number} opts.edgeFeatherPx - Edge feather amount.
	 * @param {boolean} opts.showProgress - Draw the progress bar.
	 * @return {void}
	 */
	render(opts) {
		const gl = this.gl
		const {
			videoEl,
			mask,
			bgSource,
			refreshBg,
			mode,
			outW,
			outH,
			edgeFeatherPx = 5,
			showProgress = false,
		} = opts

		// Validate dimensions
		if (!outW || !outH || outW <= 0 || outH <= 0) {
			return
		}

		// Resize canvas if needed
		if (this.canvas.width !== outW || this.canvas.height !== outH) {
			this.canvas.width = outW
			this.canvas.height = outH
		}

		// Upload video frame
		this._upload(this.texFrame, videoEl)

		// Shortcut if we have no mask or mode is no effects
		if (mode === -1 || !mask) {
			this._renderWithoutEffects(outW, outH)
			if (showProgress) {
				this._drawProgressBar(outW, outH)
			}
			return
		}

		// Allocate mask filtered texture
		gl.bindTexture(gl.TEXTURE_2D, this.texMaskFiltered)
		gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, outW, outH, 0, gl.RGBA, gl.UNSIGNED_BYTE, null)

		// Upload and process mask
		if (mask) {
			this._blitTextureToCanvas(mask)
			this._upload(this.texMask, mask.canvas, { flipY: true })
		}

		// Upload background if in image mode
		if (mode === 0 && bgSource && refreshBg) {
			this._upload(this.texBg, bgSource)
		}

		gl.bindVertexArray(this.vertexArray)

		// Apply bilateral filter to mask
		if (mask) {
			this._applyBilateralFilter(outW, outH)
		}

		// Apply multi-pass blur if in blur mode
		if (mode === 1) {
			this._applyMultiPassBlur(outW, outH)
		}

		// Final blend pass
		gl.bindFramebuffer(gl.FRAMEBUFFER, null)
		gl.viewport(0, 0, outW, outH)
		gl.useProgram(this.progBlend)
		this._setupVertexAttributes(this.progBlend)

		// Set blend uniforms
		this.coverage = [0.45, 0.7 - (edgeFeatherPx * 0.01)]
		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_inputFrame'), 0)
		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_personMask'), 1)
		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_blurredFrame'), 2)
		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_background'), 3)
		gl.uniform2f(gl.getUniformLocation(this.progBlend, 'u_coverage'), this.coverage[0], this.coverage[1])
		gl.uniform1f(gl.getUniformLocation(this.progBlend, 'u_lightWrapping'), this.lightWrapping)
		gl.uniform1i(gl.getUniformLocation(this.progBlend, 'u_mode'), mode)

		// Bind textures for final blend
		gl.activeTexture(gl.TEXTURE0)
		gl.bindTexture(gl.TEXTURE_2D, this.texFrame)
		gl.activeTexture(gl.TEXTURE1)
		gl.bindTexture(gl.TEXTURE_2D, this.texMaskFiltered)
		gl.activeTexture(gl.TEXTURE2)
		gl.bindTexture(gl.TEXTURE_2D, this.texBlurred2)
		gl.activeTexture(gl.TEXTURE3)
		gl.bindTexture(gl.TEXTURE_2D, this.texBg)

		gl.clearColor(0, 0, 0, 1)
		gl.clear(gl.COLOR_BUFFER_BIT)
		gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4)

		if (showProgress) {
			this._drawProgressBar(outW, outH)
		}
	}

	/**
	 * Release all GL resources.
	 *
	 * @return {void}
	 */
	dispose() {
		const gl = this.gl
		if (!gl) {
			return
		}

		// Delete textures
		gl.deleteTexture(this.texFrame)
		gl.deleteTexture(this.texMask)
		gl.deleteTexture(this.texMaskFiltered)
		gl.deleteTexture(this.texBg)
		gl.deleteTexture(this.texBlurred1)
		gl.deleteTexture(this.texBlurred2)

		// Delete buffers
		gl.deleteBuffer(this.positionBuffer)
		gl.deleteBuffer(this.texCoordBuffer)

		// Delete programs
		gl.deleteProgram(this.progBilateral)
		gl.deleteProgram(this.progBlur)
		gl.deleteProgram(this.progBlend)
		gl.deleteProgram(this.progHUD)

		// Delete framebuffers
		gl.deleteFramebuffer(this.fboMask)
		gl.deleteFramebuffer(this.fboBlur1)
		gl.deleteFramebuffer(this.fboBlur2)

		// Delete vertex array
		gl.deleteVertexArray(this.vertexArray)

		// Clear references
		this.texFrame = this.texMask = this.texMaskFiltered = null
		this.texBg = this.texBlurred1 = this.texBlurred2 = null
		this.positionBuffer = this.texCoordBuffer = this.blitBuf = null
		this.progBilateral = this.progBlur = this.progBlend = this.progBlit = null
		this.fboMask = this.fboBlur1 = this.fboBlur2 = null
		this.vertexArray = null
	}
}
