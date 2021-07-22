"use strict";
var __makeTemplateObject = (this && this.__makeTemplateObject) || function (cooked, raw) {
    if (Object.defineProperty) { Object.defineProperty(cooked, "raw", { value: raw }); } else { cooked.raw = raw; }
    return cooked;
};
exports.__esModule = true;
exports.buildBackgroundImageStage = void 0;
var webglHelper_1 = require("../helpers/webglHelper");
function buildBackgroundImageStage(gl, positionBuffer, texCoordBuffer, personMaskTexture, backgroundImage, canvas) {
    var vertexShaderSource = webglHelper_1.glsl(templateObject_1 || (templateObject_1 = __makeTemplateObject(["#version 300 es\n\n    uniform vec2 u_backgroundScale;\n    uniform vec2 u_backgroundOffset;\n\n    in vec2 a_position;\n    in vec2 a_texCoord;\n\n    out vec2 v_texCoord;\n    out vec2 v_backgroundCoord;\n\n    void main() {\n      // Flipping Y is required when rendering to canvas\n      gl_Position = vec4(a_position * vec2(1.0, -1.0), 0.0, 1.0);\n      v_texCoord = a_texCoord;\n      v_backgroundCoord = a_texCoord * u_backgroundScale + u_backgroundOffset;\n    }\n  "], ["#version 300 es\n\n    uniform vec2 u_backgroundScale;\n    uniform vec2 u_backgroundOffset;\n\n    in vec2 a_position;\n    in vec2 a_texCoord;\n\n    out vec2 v_texCoord;\n    out vec2 v_backgroundCoord;\n\n    void main() {\n      // Flipping Y is required when rendering to canvas\n      gl_Position = vec4(a_position * vec2(1.0, -1.0), 0.0, 1.0);\n      v_texCoord = a_texCoord;\n      v_backgroundCoord = a_texCoord * u_backgroundScale + u_backgroundOffset;\n    }\n  "])));
    var fragmentShaderSource = webglHelper_1.glsl(templateObject_2 || (templateObject_2 = __makeTemplateObject(["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n    uniform sampler2D u_personMask;\n    uniform sampler2D u_background;\n    uniform vec2 u_coverage;\n    uniform float u_lightWrapping;\n    uniform float u_blendMode;\n\n    in vec2 v_texCoord;\n    in vec2 v_backgroundCoord;\n\n    out vec4 outColor;\n\n    vec3 screen(vec3 a, vec3 b) {\n      return 1.0 - (1.0 - a) * (1.0 - b);\n    }\n\n    vec3 linearDodge(vec3 a, vec3 b) {\n      return a + b;\n    }\n\n    void main() {\n      vec3 frameColor = texture(u_inputFrame, v_texCoord).rgb;\n      vec3 backgroundColor = texture(u_background, v_backgroundCoord).rgb;\n      float personMask = texture(u_personMask, v_texCoord).a;\n      float lightWrapMask = 1.0 - max(0.0, personMask - u_coverage.y) / (1.0 - u_coverage.y);\n      vec3 lightWrap = u_lightWrapping * lightWrapMask * backgroundColor;\n      frameColor = u_blendMode * linearDodge(frameColor, lightWrap) +\n        (1.0 - u_blendMode) * screen(frameColor, lightWrap);\n      personMask = smoothstep(u_coverage.x, u_coverage.y, personMask);\n      outColor = vec4(frameColor * personMask + backgroundColor * (1.0 - personMask), 1.0);\n    }\n  "], ["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n    uniform sampler2D u_personMask;\n    uniform sampler2D u_background;\n    uniform vec2 u_coverage;\n    uniform float u_lightWrapping;\n    uniform float u_blendMode;\n\n    in vec2 v_texCoord;\n    in vec2 v_backgroundCoord;\n\n    out vec4 outColor;\n\n    vec3 screen(vec3 a, vec3 b) {\n      return 1.0 - (1.0 - a) * (1.0 - b);\n    }\n\n    vec3 linearDodge(vec3 a, vec3 b) {\n      return a + b;\n    }\n\n    void main() {\n      vec3 frameColor = texture(u_inputFrame, v_texCoord).rgb;\n      vec3 backgroundColor = texture(u_background, v_backgroundCoord).rgb;\n      float personMask = texture(u_personMask, v_texCoord).a;\n      float lightWrapMask = 1.0 - max(0.0, personMask - u_coverage.y) / (1.0 - u_coverage.y);\n      vec3 lightWrap = u_lightWrapping * lightWrapMask * backgroundColor;\n      frameColor = u_blendMode * linearDodge(frameColor, lightWrap) +\n        (1.0 - u_blendMode) * screen(frameColor, lightWrap);\n      personMask = smoothstep(u_coverage.x, u_coverage.y, personMask);\n      outColor = vec4(frameColor * personMask + backgroundColor * (1.0 - personMask), 1.0);\n    }\n  "])));
    var outputWidth = canvas.width, outputHeight = canvas.height;
    var outputRatio = outputWidth / outputHeight;
    var vertexShader = webglHelper_1.compileShader(gl, gl.VERTEX_SHADER, vertexShaderSource);
    var fragmentShader = webglHelper_1.compileShader(gl, gl.FRAGMENT_SHADER, fragmentShaderSource);
    var program = webglHelper_1.createPiplelineStageProgram(gl, vertexShader, fragmentShader, positionBuffer, texCoordBuffer);
    var backgroundScaleLocation = gl.getUniformLocation(program, 'u_backgroundScale');
    var backgroundOffsetLocation = gl.getUniformLocation(program, 'u_backgroundOffset');
    var inputFrameLocation = gl.getUniformLocation(program, 'u_inputFrame');
    var personMaskLocation = gl.getUniformLocation(program, 'u_personMask');
    var backgroundLocation = gl.getUniformLocation(program, 'u_background');
    var coverageLocation = gl.getUniformLocation(program, 'u_coverage');
    var lightWrappingLocation = gl.getUniformLocation(program, 'u_lightWrapping');
    var blendModeLocation = gl.getUniformLocation(program, 'u_blendMode');
    gl.useProgram(program);
    gl.uniform2f(backgroundScaleLocation, 1, 1);
    gl.uniform2f(backgroundOffsetLocation, 0, 0);
    gl.uniform1i(inputFrameLocation, 0);
    gl.uniform1i(personMaskLocation, 1);
    gl.uniform2f(coverageLocation, 0, 1);
    gl.uniform1f(lightWrappingLocation, 0);
    gl.uniform1f(blendModeLocation, 0);
    var backgroundTexture = null;
    // TODO Find a better to handle background being loaded
    if (backgroundImage === null || backgroundImage === void 0 ? void 0 : backgroundImage.complete) {
        updateBackgroundImage(backgroundImage);
    }
    else if (backgroundImage) {
        backgroundImage.onload = function () {
            updateBackgroundImage(backgroundImage);
        };
    }
    function render() {
        gl.viewport(0, 0, outputWidth, outputHeight);
        gl.useProgram(program);
        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_2D, personMaskTexture);
        if (backgroundTexture !== null) {
            gl.activeTexture(gl.TEXTURE2);
            gl.bindTexture(gl.TEXTURE_2D, backgroundTexture);
            // TODO Handle correctly the background not loaded yet
            gl.uniform1i(backgroundLocation, 2);
        }
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }
    function updateBackgroundImage(backgroundImage) {
        backgroundTexture = webglHelper_1.createTexture(gl, gl.RGBA8, backgroundImage.naturalWidth, backgroundImage.naturalHeight, gl.LINEAR, gl.LINEAR);
        gl.texSubImage2D(gl.TEXTURE_2D, 0, 0, 0, backgroundImage.naturalWidth, backgroundImage.naturalHeight, gl.RGBA, gl.UNSIGNED_BYTE, backgroundImage);
        var xOffset = 0;
        var yOffset = 0;
        var backgroundWidth = backgroundImage.naturalWidth;
        var backgroundHeight = backgroundImage.naturalHeight;
        var backgroundRatio = backgroundWidth / backgroundHeight;
        if (backgroundRatio < outputRatio) {
            backgroundHeight = backgroundWidth / outputRatio;
            yOffset = (backgroundImage.naturalHeight - backgroundHeight) / 2;
        }
        else {
            backgroundWidth = backgroundHeight * outputRatio;
            xOffset = (backgroundImage.naturalWidth - backgroundWidth) / 2;
        }
        var xScale = backgroundWidth / backgroundImage.naturalWidth;
        var yScale = backgroundHeight / backgroundImage.naturalHeight;
        xOffset /= backgroundImage.naturalWidth;
        yOffset /= backgroundImage.naturalHeight;
        gl.uniform2f(backgroundScaleLocation, xScale, yScale);
        gl.uniform2f(backgroundOffsetLocation, xOffset, yOffset);
    }
    function updateCoverage(coverage) {
        gl.useProgram(program);
        gl.uniform2f(coverageLocation, coverage[0], coverage[1]);
    }
    function updateLightWrapping(lightWrapping) {
        gl.useProgram(program);
        gl.uniform1f(lightWrappingLocation, lightWrapping);
    }
    function updateBlendMode(blendMode) {
        gl.useProgram(program);
        gl.uniform1f(blendModeLocation, blendMode === 'screen' ? 0 : 1);
    }
    function cleanUp() {
        gl.deleteTexture(backgroundTexture);
        gl.deleteProgram(program);
        gl.deleteShader(fragmentShader);
        gl.deleteShader(vertexShader);
    }
    return {
        render: render,
        updateCoverage: updateCoverage,
        updateLightWrapping: updateLightWrapping,
        updateBlendMode: updateBlendMode,
        cleanUp: cleanUp
    };
}
exports.buildBackgroundImageStage = buildBackgroundImageStage;
var templateObject_1, templateObject_2;
