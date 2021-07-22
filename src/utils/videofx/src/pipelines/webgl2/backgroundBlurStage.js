"use strict";
var __makeTemplateObject = (this && this.__makeTemplateObject) || function (cooked, raw) {
    if (Object.defineProperty) { Object.defineProperty(cooked, "raw", { value: raw }); } else { cooked.raw = raw; }
    return cooked;
};
exports.__esModule = true;
exports.buildBackgroundBlurStage = void 0;
var webglHelper_1 = require("../helpers/webglHelper");
function buildBackgroundBlurStage(gl, vertexShader, positionBuffer, texCoordBuffer, personMaskTexture, canvas) {
    var blurPass = buildBlurPass(gl, vertexShader, positionBuffer, texCoordBuffer, personMaskTexture, canvas);
    var blendPass = buildBlendPass(gl, positionBuffer, texCoordBuffer, canvas);
    function render() {
        blurPass.render();
        blendPass.render();
    }
    function updateCoverage(coverage) {
        blendPass.updateCoverage(coverage);
    }
    function cleanUp() {
        blendPass.cleanUp();
        blurPass.cleanUp();
    }
    return {
        render: render,
        updateCoverage: updateCoverage,
        cleanUp: cleanUp
    };
}
exports.buildBackgroundBlurStage = buildBackgroundBlurStage;
function buildBlurPass(gl, vertexShader, positionBuffer, texCoordBuffer, personMaskTexture, canvas) {
    var fragmentShaderSource = webglHelper_1.glsl(templateObject_1 || (templateObject_1 = __makeTemplateObject(["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n    uniform sampler2D u_personMask;\n    uniform vec2 u_texelSize;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    const float offset[5] = float[](0.0, 1.0, 2.0, 3.0, 4.0);\n    const float weight[5] = float[](0.2270270270, 0.1945945946, 0.1216216216,\n      0.0540540541, 0.0162162162);\n\n    void main() {\n      vec4 centerColor = texture(u_inputFrame, v_texCoord);\n      float personMask = texture(u_personMask, v_texCoord).a;\n\n      vec4 frameColor = centerColor * weight[0] * (1.0 - personMask);\n\n      for (int i = 1; i < 5; i++) {\n        vec2 offset = vec2(offset[i]) * u_texelSize;\n\n        vec2 texCoord = v_texCoord + offset;\n        frameColor += texture(u_inputFrame, texCoord) * weight[i] *\n          (1.0 - texture(u_personMask, texCoord).a);\n\n        texCoord = v_texCoord - offset;\n        frameColor += texture(u_inputFrame, texCoord) * weight[i] *\n          (1.0 - texture(u_personMask, texCoord).a);\n      }\n      outColor = vec4(frameColor.rgb + (1.0 - frameColor.a) * centerColor.rgb, 1.0);\n    }\n  "], ["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n    uniform sampler2D u_personMask;\n    uniform vec2 u_texelSize;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    const float offset[5] = float[](0.0, 1.0, 2.0, 3.0, 4.0);\n    const float weight[5] = float[](0.2270270270, 0.1945945946, 0.1216216216,\n      0.0540540541, 0.0162162162);\n\n    void main() {\n      vec4 centerColor = texture(u_inputFrame, v_texCoord);\n      float personMask = texture(u_personMask, v_texCoord).a;\n\n      vec4 frameColor = centerColor * weight[0] * (1.0 - personMask);\n\n      for (int i = 1; i < 5; i++) {\n        vec2 offset = vec2(offset[i]) * u_texelSize;\n\n        vec2 texCoord = v_texCoord + offset;\n        frameColor += texture(u_inputFrame, texCoord) * weight[i] *\n          (1.0 - texture(u_personMask, texCoord).a);\n\n        texCoord = v_texCoord - offset;\n        frameColor += texture(u_inputFrame, texCoord) * weight[i] *\n          (1.0 - texture(u_personMask, texCoord).a);\n      }\n      outColor = vec4(frameColor.rgb + (1.0 - frameColor.a) * centerColor.rgb, 1.0);\n    }\n  "])));
    var scale = 0.2;
    var outputWidth = canvas.width * scale;
    var outputHeight = canvas.height * scale;
    var texelWidth = 1 / outputWidth;
    var texelHeight = 1 / outputHeight;
    var fragmentShader = webglHelper_1.compileShader(gl, gl.FRAGMENT_SHADER, fragmentShaderSource);
    var program = webglHelper_1.createPiplelineStageProgram(gl, vertexShader, fragmentShader, positionBuffer, texCoordBuffer);
    var inputFrameLocation = gl.getUniformLocation(program, 'u_inputFrame');
    var personMaskLocation = gl.getUniformLocation(program, 'u_personMask');
    var texelSizeLocation = gl.getUniformLocation(program, 'u_texelSize');
    var texture1 = webglHelper_1.createTexture(gl, gl.RGBA8, outputWidth, outputHeight, gl.NEAREST, gl.LINEAR);
    var texture2 = webglHelper_1.createTexture(gl, gl.RGBA8, outputWidth, outputHeight, gl.NEAREST, gl.LINEAR);
    var frameBuffer1 = gl.createFramebuffer();
    gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer1);
    gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, texture1, 0);
    var frameBuffer2 = gl.createFramebuffer();
    gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer2);
    gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, texture2, 0);
    gl.useProgram(program);
    gl.uniform1i(personMaskLocation, 1);
    function render() {
        gl.viewport(0, 0, outputWidth, outputHeight);
        gl.useProgram(program);
        gl.uniform1i(inputFrameLocation, 0);
        gl.activeTexture(gl.TEXTURE1);
        gl.bindTexture(gl.TEXTURE_2D, personMaskTexture);
        for (var i = 0; i < 3; i++) {
            gl.uniform2f(texelSizeLocation, 0, texelHeight);
            gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer1);
            gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
            gl.activeTexture(gl.TEXTURE2);
            gl.bindTexture(gl.TEXTURE_2D, texture1);
            gl.uniform1i(inputFrameLocation, 2);
            gl.uniform2f(texelSizeLocation, texelWidth, 0);
            gl.bindFramebuffer(gl.FRAMEBUFFER, frameBuffer2);
            gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
            gl.bindTexture(gl.TEXTURE_2D, texture2);
        }
    }
    function cleanUp() {
        gl.deleteFramebuffer(frameBuffer2);
        gl.deleteFramebuffer(frameBuffer1);
        gl.deleteTexture(texture2);
        gl.deleteTexture(texture1);
        gl.deleteProgram(program);
        gl.deleteShader(fragmentShader);
    }
    return {
        render: render,
        cleanUp: cleanUp
    };
}
function buildBlendPass(gl, positionBuffer, texCoordBuffer, canvas) {
    var vertexShaderSource = webglHelper_1.glsl(templateObject_2 || (templateObject_2 = __makeTemplateObject(["#version 300 es\n\n    in vec2 a_position;\n    in vec2 a_texCoord;\n\n    out vec2 v_texCoord;\n\n    void main() {\n      // Flipping Y is required when rendering to canvas\n      gl_Position = vec4(a_position * vec2(1.0, -1.0), 0.0, 1.0);\n      v_texCoord = a_texCoord;\n    }\n  "], ["#version 300 es\n\n    in vec2 a_position;\n    in vec2 a_texCoord;\n\n    out vec2 v_texCoord;\n\n    void main() {\n      // Flipping Y is required when rendering to canvas\n      gl_Position = vec4(a_position * vec2(1.0, -1.0), 0.0, 1.0);\n      v_texCoord = a_texCoord;\n    }\n  "])));
    var fragmentShaderSource = webglHelper_1.glsl(templateObject_3 || (templateObject_3 = __makeTemplateObject(["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n    uniform sampler2D u_personMask;\n    uniform sampler2D u_blurredInputFrame;\n    uniform vec2 u_coverage;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    void main() {\n      vec3 color = texture(u_inputFrame, v_texCoord).rgb;\n      vec3 blurredColor = texture(u_blurredInputFrame, v_texCoord).rgb;\n      float personMask = texture(u_personMask, v_texCoord).a;\n      personMask = smoothstep(u_coverage.x, u_coverage.y, personMask);\n      outColor = vec4(mix(blurredColor, color, personMask), 1.0);\n    }\n  "], ["#version 300 es\n\n    precision highp float;\n\n    uniform sampler2D u_inputFrame;\n    uniform sampler2D u_personMask;\n    uniform sampler2D u_blurredInputFrame;\n    uniform vec2 u_coverage;\n\n    in vec2 v_texCoord;\n\n    out vec4 outColor;\n\n    void main() {\n      vec3 color = texture(u_inputFrame, v_texCoord).rgb;\n      vec3 blurredColor = texture(u_blurredInputFrame, v_texCoord).rgb;\n      float personMask = texture(u_personMask, v_texCoord).a;\n      personMask = smoothstep(u_coverage.x, u_coverage.y, personMask);\n      outColor = vec4(mix(blurredColor, color, personMask), 1.0);\n    }\n  "])));
    var outputWidth = canvas.width, outputHeight = canvas.height;
    var vertexShader = webglHelper_1.compileShader(gl, gl.VERTEX_SHADER, vertexShaderSource);
    var fragmentShader = webglHelper_1.compileShader(gl, gl.FRAGMENT_SHADER, fragmentShaderSource);
    var program = webglHelper_1.createPiplelineStageProgram(gl, vertexShader, fragmentShader, positionBuffer, texCoordBuffer);
    var inputFrameLocation = gl.getUniformLocation(program, 'u_inputFrame');
    var personMaskLocation = gl.getUniformLocation(program, 'u_personMask');
    var blurredInputFrame = gl.getUniformLocation(program, 'u_blurredInputFrame');
    var coverageLocation = gl.getUniformLocation(program, 'u_coverage');
    gl.useProgram(program);
    gl.uniform1i(inputFrameLocation, 0);
    gl.uniform1i(personMaskLocation, 1);
    gl.uniform1i(blurredInputFrame, 2);
    gl.uniform2f(coverageLocation, 0, 1);
    function render() {
        gl.viewport(0, 0, outputWidth, outputHeight);
        gl.useProgram(program);
        gl.bindFramebuffer(gl.FRAMEBUFFER, null);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }
    function updateCoverage(coverage) {
        gl.useProgram(program);
        gl.uniform2f(coverageLocation, coverage[0], coverage[1]);
    }
    function cleanUp() {
        gl.deleteProgram(program);
        gl.deleteShader(fragmentShader);
        gl.deleteShader(vertexShader);
    }
    return {
        render: render,
        updateCoverage: updateCoverage,
        cleanUp: cleanUp
    };
}
var templateObject_1, templateObject_2, templateObject_3;
