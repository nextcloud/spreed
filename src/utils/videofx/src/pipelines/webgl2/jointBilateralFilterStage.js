"use strict";
var __makeTemplateObject = (this && this.__makeTemplateObject) || function (cooked, raw) {
    if (Object.defineProperty) { Object.defineProperty(cooked, "raw", { value: raw }); } else { cooked.raw = raw; }
    return cooked;
};
exports.__esModule = true;
exports.buildJointBilateralFilterStage = void 0;
var segmentationHelper_1 = require("../../core/helpers/segmentationHelper");
var webglHelper_1 = require("../helpers/webglHelper");
function buildJointBilateralFilterStage(gl, vertexShader, positionBuffer, texCoordBuffer, inputTexture, segmentationConfig, outputTexture, canvas) {
    var fragmentShaderSource = webglHelper_1.glsl(templateObject_1 || (templateObject_1 = __makeTemplateObject(["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n    uniform sampler2D u_segmentationMask;\n    uniform vec2 u_texelSize;\n    uniform float u_step;\n    uniform float u_radius;\n    uniform float u_offset;\n    uniform float u_sigmaTexel;\n    uniform float u_sigmaColor;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    float gaussian(float x, float sigma) {\n      float coeff = -0.5 / (sigma * sigma * 4.0 + 1.0e-6);\n      return exp((x * x) * coeff);\n    }\n\n    void main() {\n      vec2 centerCoord = v_texCoord;\n      vec3 centerColor = texture(u_inputFrame, centerCoord).rgb;\n      float newVal = 0.0;\n\n      float spaceWeight = 0.0;\n      float colorWeight = 0.0;\n      float totalWeight = 0.0;\n\n      // Subsample kernel space.\n      for (float i = -u_radius + u_offset; i <= u_radius; i += u_step) {\n        for (float j = -u_radius + u_offset; j <= u_radius; j += u_step) {\n          vec2 shift = vec2(j, i) * u_texelSize;\n          vec2 coord = vec2(centerCoord + shift);\n          vec3 frameColor = texture(u_inputFrame, coord).rgb;\n          float outVal = texture(u_segmentationMask, coord).a;\n\n          spaceWeight = gaussian(distance(centerCoord, coord), u_sigmaTexel);\n          colorWeight = gaussian(distance(centerColor, frameColor), u_sigmaColor);\n          totalWeight += spaceWeight * colorWeight;\n\n          newVal += spaceWeight * colorWeight * outVal;\n        }\n      }\n      newVal /= totalWeight;\n\n      outColor = vec4(vec3(0.0), newVal);\n    }\n  "], ["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n    uniform sampler2D u_segmentationMask;\n    uniform vec2 u_texelSize;\n    uniform float u_step;\n    uniform float u_radius;\n    uniform float u_offset;\n    uniform float u_sigmaTexel;\n    uniform float u_sigmaColor;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    float gaussian(float x, float sigma) {\n      float coeff = -0.5 / (sigma * sigma * 4.0 + 1.0e-6);\n      return exp((x * x) * coeff);\n    }\n\n    void main() {\n      vec2 centerCoord = v_texCoord;\n      vec3 centerColor = texture(u_inputFrame, centerCoord).rgb;\n      float newVal = 0.0;\n\n      float spaceWeight = 0.0;\n      float colorWeight = 0.0;\n      float totalWeight = 0.0;\n\n      // Subsample kernel space.\n      for (float i = -u_radius + u_offset; i <= u_radius; i += u_step) {\n        for (float j = -u_radius + u_offset; j <= u_radius; j += u_step) {\n          vec2 shift = vec2(j, i) * u_texelSize;\n          vec2 coord = vec2(centerCoord + shift);\n          vec3 frameColor = texture(u_inputFrame, coord).rgb;\n          float outVal = texture(u_segmentationMask, coord).a;\n\n          spaceWeight = gaussian(distance(centerCoord, coord), u_sigmaTexel);\n          colorWeight = gaussian(distance(centerColor, frameColor), u_sigmaColor);\n          totalWeight += spaceWeight * colorWeight;\n\n          newVal += spaceWeight * colorWeight * outVal;\n        }\n      }\n      newVal /= totalWeight;\n\n      outColor = vec4(vec3(0.0), newVal);\n    }\n  "])));
    var _a = segmentationHelper_1.inputResolutions[segmentationConfig.inputResolution], segmentationWidth = _a[0], segmentationHeight = _a[1];
    var outputWidth = canvas.width, outputHeight = canvas.height;
    var texelWidth = 1 / outputWidth;
    var texelHeight = 1 / outputHeight;
    var fragmentShader = webglHelper_1.compileShader(gl, gl.FRAGMENT_SHADER, fragmentShaderSource);
    var program = webglHelper_1.createPiplelineStageProgram(gl, vertexShader, fragmentShader, positionBuffer, texCoordBuffer);
    var inputFrameLocation = gl.getUniformLocation(program, 'u_inputFrame');
    var segmentationMaskLocation = gl.getUniformLocation(program, 'u_segmentationMask');
    var texelSizeLocation = gl.getUniformLocation(program, 'u_texelSize');
    var stepLocation = gl.getUniformLocation(program, 'u_step');
    var radiusLocation = gl.getUniformLocation(program, 'u_radius');
    var offsetLocation = gl.getUniformLocation(program, 'u_offset');
    var sigmaTexelLocation = gl.getUniformLocation(program, 'u_sigmaTexel');
    var sigmaColorLocation = gl.getUniformLocation(program, 'u_sigmaColor');
    var frameBuffer = gl.createFramebuffer();
    gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer);
    gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, outputTexture, 0);
    gl.useProgram(program);
    gl.uniform1i(inputFrameLocation, 0);
    gl.uniform1i(segmentationMaskLocation, 1);
    gl.uniform2f(texelSizeLocation, texelWidth, texelHeight);
    // Ensures default values are configured to prevent infinite
    // loop in fragment shader
    updateSigmaSpace(0);
    updateSigmaColor(0);
    function render() {
        gl.viewport(0, 0, outputWidth, outputHeight);
        gl.useProgram(program);
        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_2D, inputTexture);
        gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }
    function updateSigmaSpace(sigmaSpace) {
        sigmaSpace *= Math.max(outputWidth / segmentationWidth, outputHeight / segmentationHeight);
        var kSparsityFactor = 0.66; // Higher is more sparse.
        var sparsity = Math.max(1, Math.sqrt(sigmaSpace) * kSparsityFactor);
        var step = sparsity;
        var radius = sigmaSpace;
        var offset = step > 1 ? step * 0.5 : 0;
        var sigmaTexel = Math.max(texelWidth, texelHeight) * sigmaSpace;
        gl.useProgram(program);
        gl.uniform1f(stepLocation, step);
        gl.uniform1f(radiusLocation, radius);
        gl.uniform1f(offsetLocation, offset);
        gl.uniform1f(sigmaTexelLocation, sigmaTexel);
    }
    function updateSigmaColor(sigmaColor) {
        gl.useProgram(program);
        gl.uniform1f(sigmaColorLocation, sigmaColor);
    }
    function cleanUp() {
        gl.deleteFramebuffer(frameBuffer);
        gl.deleteProgram(program);
        gl.deleteShader(fragmentShader);
    }
    return { render: render, updateSigmaSpace: updateSigmaSpace, updateSigmaColor: updateSigmaColor, cleanUp: cleanUp };
}
exports.buildJointBilateralFilterStage = buildJointBilateralFilterStage;
var templateObject_1;
