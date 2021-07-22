"use strict";
var __makeTemplateObject = (this && this.__makeTemplateObject) || function (cooked, raw) {
    if (Object.defineProperty) { Object.defineProperty(cooked, "raw", { value: raw }); } else { cooked.raw = raw; }
    return cooked;
};
exports.__esModule = true;
exports.buildSoftmaxStage = void 0;
var segmentationHelper_1 = require("../../core/helpers/segmentationHelper");
var webglHelper_1 = require("../helpers/webglHelper");
function buildSoftmaxStage(gl, vertexShader, positionBuffer, texCoordBuffer, segmentationConfig, tflite, outputTexture) {
    var fragmentShaderSource = webglHelper_1.glsl(templateObject_1 || (templateObject_1 = __makeTemplateObject(["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputSegmentation;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    void main() {\n      vec2 segmentation = texture(u_inputSegmentation, v_texCoord).rg;\n      float shift = max(segmentation.r, segmentation.g);\n      float backgroundExp = exp(segmentation.r - shift);\n      float personExp = exp(segmentation.g - shift);\n      outColor = vec4(vec3(0.0), personExp / (backgroundExp + personExp));\n    }\n  "], ["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputSegmentation;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    void main() {\n      vec2 segmentation = texture(u_inputSegmentation, v_texCoord).rg;\n      float shift = max(segmentation.r, segmentation.g);\n      float backgroundExp = exp(segmentation.r - shift);\n      float personExp = exp(segmentation.g - shift);\n      outColor = vec4(vec3(0.0), personExp / (backgroundExp + personExp));\n    }\n  "
        // TFLite memory will be accessed as float32
    ])));
    // TFLite memory will be accessed as float32
    var tfliteOutputMemoryOffset = tflite._getOutputMemoryOffset() / 4;
    var _a = segmentationHelper_1.inputResolutions[segmentationConfig.inputResolution], segmentationWidth = _a[0], segmentationHeight = _a[1];
    var fragmentShader = webglHelper_1.compileShader(gl, gl.FRAGMENT_SHADER, fragmentShaderSource);
    var program = webglHelper_1.createPiplelineStageProgram(gl, vertexShader, fragmentShader, positionBuffer, texCoordBuffer);
    var inputLocation = gl.getUniformLocation(program, 'u_inputSegmentation');
    var inputTexture = webglHelper_1.createTexture(gl, gl.RG32F, segmentationWidth, segmentationHeight);
    var frameBuffer = gl.createFramebuffer();
    gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer);
    gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, outputTexture, 0);
    gl.useProgram(program);
    gl.uniform1i(inputLocation, 1);
    function render() {
        gl.viewport(0, 0, segmentationWidth, segmentationHeight);
        gl.useProgram(program);
        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_2D, inputTexture);
        gl.texSubImage2D(gl.TEXTURE_2D, 0, 0, 0, segmentationWidth, segmentationHeight, gl.RG, gl.FLOAT, tflite.HEAPF32, tfliteOutputMemoryOffset);
        gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }
    function cleanUp() {
        gl.deleteFramebuffer(frameBuffer);
        gl.deleteTexture(inputTexture);
        gl.deleteProgram(program);
        gl.deleteShader(fragmentShader);
    }
    return { render: render, cleanUp: cleanUp };
}
exports.buildSoftmaxStage = buildSoftmaxStage;
var templateObject_1;
