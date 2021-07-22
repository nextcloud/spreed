"use strict";
var __makeTemplateObject = (this && this.__makeTemplateObject) || function (cooked, raw) {
    if (Object.defineProperty) { Object.defineProperty(cooked, "raw", { value: raw }); } else { cooked.raw = raw; }
    return cooked;
};
exports.__esModule = true;
exports.buildResizingStage = void 0;
var segmentationHelper_1 = require("../../core/helpers/segmentationHelper");
var webglHelper_1 = require("../helpers/webglHelper");
function buildResizingStage(gl, vertexShader, positionBuffer, texCoordBuffer, segmentationConfig, tflite) {
    var fragmentShaderSource = webglHelper_1.glsl(templateObject_1 || (templateObject_1 = __makeTemplateObject(["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    void main() {\n      outColor = texture(u_inputFrame, v_texCoord);\n    }\n  "], ["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    void main() {\n      outColor = texture(u_inputFrame, v_texCoord);\n    }\n  "
        // TFLite memory will be accessed as float32
    ])));
    // TFLite memory will be accessed as float32
    var tfliteInputMemoryOffset = tflite._getInputMemoryOffset() / 4;
    var _a = segmentationHelper_1.inputResolutions[segmentationConfig.inputResolution], outputWidth = _a[0], outputHeight = _a[1];
    var outputPixelCount = outputWidth * outputHeight;
    var fragmentShader = webglHelper_1.compileShader(gl, gl.FRAGMENT_SHADER, fragmentShaderSource);
    var program = webglHelper_1.createPiplelineStageProgram(gl, vertexShader, fragmentShader, positionBuffer, texCoordBuffer);
    var inputFrameLocation = gl.getUniformLocation(program, 'u_inputFrame');
    var outputTexture = webglHelper_1.createTexture(gl, gl.RGBA8, outputWidth, outputHeight);
    var frameBuffer = gl.createFramebuffer();
    gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer);
    gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, outputTexture, 0);
    var outputPixels = new Uint8Array(outputPixelCount * 4);
    gl.useProgram(program);
    gl.uniform1i(inputFrameLocation, 0);
    function render() {
        gl.viewport(0, 0, outputWidth, outputHeight);
        gl.useProgram(program);
        gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        // Downloads pixels asynchronously from GPU while rendering the current frame
        webglHelper_1.readPixelsAsync(gl, 0, 0, outputWidth, outputHeight, gl.RGBA, gl.UNSIGNED_BYTE, outputPixels);
        for (var i = 0; i < outputPixelCount; i++) {
            var tfliteIndex = tfliteInputMemoryOffset + i * 3;
            var outputIndex = i * 4;
            tflite.HEAPF32[tfliteIndex] = outputPixels[outputIndex] / 255;
            tflite.HEAPF32[tfliteIndex + 1] = outputPixels[outputIndex + 1] / 255;
            tflite.HEAPF32[tfliteIndex + 2] = outputPixels[outputIndex + 2] / 255;
        }
    }
    function cleanUp() {
        gl.deleteFramebuffer(frameBuffer);
        gl.deleteTexture(outputTexture);
        gl.deleteProgram(program);
        gl.deleteShader(fragmentShader);
    }
    return { render: render, cleanUp: cleanUp };
}
exports.buildResizingStage = buildResizingStage;
var templateObject_1;
