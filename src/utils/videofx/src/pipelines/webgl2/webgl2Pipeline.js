"use strict";
var __makeTemplateObject = (this && this.__makeTemplateObject) || function (cooked, raw) {
    if (Object.defineProperty) { Object.defineProperty(cooked, "raw", { value: raw }); } else { cooked.raw = raw; }
    return cooked;
};
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
var __generator = (this && this.__generator) || function (thisArg, body) {
    var _ = { label: 0, sent: function() { if (t[0] & 1) throw t[1]; return t[1]; }, trys: [], ops: [] }, f, y, t, g;
    return g = { next: verb(0), "throw": verb(1), "return": verb(2) }, typeof Symbol === "function" && (g[Symbol.iterator] = function() { return this; }), g;
    function verb(n) { return function (v) { return step([n, v]); }; }
    function step(op) {
        if (f) throw new TypeError("Generator is already executing.");
        while (_) try {
            if (f = 1, y && (t = op[0] & 2 ? y["return"] : op[0] ? y["throw"] || ((t = y["return"]) && t.call(y), 0) : y.next) && !(t = t.call(y, op[1])).done) return t;
            if (y = 0, t) op = [op[0] & 2, t.value];
            switch (op[0]) {
                case 0: case 1: t = op; break;
                case 4: _.label++; return { value: op[1], done: false };
                case 5: _.label++; y = op[1]; op = [0]; continue;
                case 7: op = _.ops.pop(); _.trys.pop(); continue;
                default:
                    if (!(t = _.trys, t = t.length > 0 && t[t.length - 1]) && (op[0] === 6 || op[0] === 2)) { _ = 0; continue; }
                    if (op[0] === 3 && (!t || (op[1] > t[0] && op[1] < t[3]))) { _.label = op[1]; break; }
                    if (op[0] === 6 && _.label < t[1]) { _.label = t[1]; t = op; break; }
                    if (t && _.label < t[2]) { _.label = t[2]; _.ops.push(op); break; }
                    if (t[2]) _.ops.pop();
                    _.trys.pop(); continue;
            }
            op = body.call(thisArg, _);
        } catch (e) { op = [6, e]; y = 0; } finally { f = t = 0; }
        if (op[0] & 5) throw op[1]; return { value: op[0] ? op[1] : void 0, done: true };
    }
};
exports.__esModule = true;
exports.buildWebGL2Pipeline = void 0;
var segmentationHelper_1 = require("../../core/helpers/segmentationHelper");
var webglHelper_1 = require("../helpers/webglHelper");
var backgroundBlurStage_1 = require("./backgroundBlurStage");
var jointBilateralFilterStage_1 = require("./jointBilateralFilterStage");
var resizingStage_1 = require("./resizingStage");
var softmaxStage_1 = require("./softmaxStage");
function buildWebGL2Pipeline(video, backgroundConfig, segmentationConfig, canvas, tflite, addFrameEvent) {
    console.log('webgl2pipline canvas');
    console.log(canvas);
    var vertexShaderSource = webglHelper_1.glsl(templateObject_1 || (templateObject_1 = __makeTemplateObject(["#version 300 es\n\n    in vec2 a_position;\n    in vec2 a_texCoord;\n\n    out vec2 v_texCoord;\n\n    void main() {\n      gl_Position = vec4(a_position, 0.0, 1.0);\n      v_texCoord = a_texCoord;\n    }\n  "], ["#version 300 es\n\n    in vec2 a_position;\n    in vec2 a_texCoord;\n\n    out vec2 v_texCoord;\n\n    void main() {\n      gl_Position = vec4(a_position, 0.0, 1.0);\n      v_texCoord = a_texCoord;\n    }\n  "])));
    var width = video.videoWidth, height = video.videoHeight;
    var frameWidth = width !== null && width !== void 0 ? width : 0;
    var frameHeight = height !== null && height !== void 0 ? height : 0;
    var _a = segmentationHelper_1.inputResolutions[segmentationConfig.inputResolution], segmentationWidth = _a[0], segmentationHeight = _a[1];
    var gl = canvas.getContext('webgl2');
    console.log('gl');
    console.log(gl);
    var vertexShader = webglHelper_1.compileShader(gl, gl.VERTEX_SHADER, vertexShaderSource);
    var vertexArray = gl.createVertexArray();
    gl.bindVertexArray(vertexArray);
    var positionBuffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1.0, -1.0, 1.0, -1.0, -1.0, 1.0, 1.0, 1.0]), gl.STATIC_DRAW);
    var texCoordBuffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, texCoordBuffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([0.0, 0.0, 1.0, 0.0, 0.0, 1.0, 1.0, 1.0]), gl.STATIC_DRAW);
    // We don't use texStorage2D here because texImage2D seems faster
    // to upload video texture than texSubImage2D even though the latter
    // is supposed to be the recommended way:
    // https://developer.mozilla.org/en-US/docs/Web/API/WebGL_API/WebGL_best_practices#use_texstorage_to_create_textures
    var inputFrameTexture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, inputFrameTexture);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
    // TODO Rename segmentation and person mask to be more specific
    var segmentationTexture = webglHelper_1.createTexture(gl, gl.RGBA8, segmentationWidth, segmentationHeight);
    var personMaskTexture = webglHelper_1.createTexture(gl, gl.RGBA8, frameWidth, frameHeight);
    var resizingStage = resizingStage_1.buildResizingStage(gl, vertexShader, positionBuffer, texCoordBuffer, segmentationConfig, tflite);
    var loadSegmentationStage = softmaxStage_1.buildSoftmaxStage(gl, vertexShader, positionBuffer, texCoordBuffer, segmentationConfig, tflite, segmentationTexture);
    var jointBilateralFilterStage = jointBilateralFilterStage_1.buildJointBilateralFilterStage(gl, vertexShader, positionBuffer, texCoordBuffer, segmentationTexture, segmentationConfig, personMaskTexture, canvas);
    var backgroundStage = backgroundBlurStage_1.buildBackgroundBlurStage(gl, vertexShader, positionBuffer, texCoordBuffer, personMaskTexture, canvas);
    function render() {
        return __awaiter(this, void 0, void 0, function () {
            return __generator(this, function (_a) {
                gl.clearColor(0, 0, 0, 0);
                gl.clear(gl.COLOR_BUFFER_BIT);
                gl.activeTexture(gl.TEXTURE0);
                gl.bindTexture(gl.TEXTURE_2D, inputFrameTexture);
                // texImage2D seems faster than texSubImage2D to upload
                // video texture
                gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, video);
                gl.bindVertexArray(vertexArray);
                resizingStage.render();
                addFrameEvent();
                tflite._runInference();
                addFrameEvent();
                loadSegmentationStage.render();
                jointBilateralFilterStage.render();
                backgroundStage.render();
                return [2 /*return*/];
            });
        });
    }
    function updatePostProcessingConfig(postProcessingConfig) {
        jointBilateralFilterStage.updateSigmaSpace(postProcessingConfig.jointBilateralFilter.sigmaSpace);
        jointBilateralFilterStage.updateSigmaColor(postProcessingConfig.jointBilateralFilter.sigmaColor);
        var backgroundBlurStage = backgroundStage;
        backgroundBlurStage.updateCoverage(postProcessingConfig.coverage);
    }
    function cleanUp() {
        backgroundStage.cleanUp();
        jointBilateralFilterStage.cleanUp();
        loadSegmentationStage.cleanUp();
        resizingStage.cleanUp();
        gl.deleteTexture(personMaskTexture);
        gl.deleteTexture(segmentationTexture);
        gl.deleteTexture(inputFrameTexture);
        gl.deleteBuffer(texCoordBuffer);
        gl.deleteBuffer(positionBuffer);
        gl.deleteVertexArray(vertexArray);
        gl.deleteShader(vertexShader);
    }
    return { render: render, updatePostProcessingConfig: updatePostProcessingConfig, cleanUp: cleanUp };
}
exports.buildWebGL2Pipeline = buildWebGL2Pipeline;
var templateObject_1;
