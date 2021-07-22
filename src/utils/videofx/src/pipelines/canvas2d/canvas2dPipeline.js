"use strict";
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
exports.buildCanvas2dPipeline = void 0;
var segmentationHelper_1 = require("../../core/helpers/segmentationHelper");
function buildCanvas2dPipeline(sourcePlayback, backgroundConfig, segmentationConfig, canvas, bodyPix, tflite, addFrameEvent) {
    console.log('canvas2dPipeline is called');
    var ctx = canvas.getContext('2d');
    var _a = segmentationHelper_1.inputResolutions[segmentationConfig.inputResolution], segmentationWidth = _a[0], segmentationHeight = _a[1];
    var segmentationPixelCount = segmentationWidth * segmentationHeight;
    var segmentationMask = new ImageData(segmentationWidth, segmentationHeight);
    var segmentationMaskCanvas = document.createElement('canvas');
    segmentationMaskCanvas.width = segmentationWidth;
    segmentationMaskCanvas.height = segmentationHeight;
    var segmentationMaskCtx = segmentationMaskCanvas.getContext('2d');
    var inputMemoryOffset = tflite._getInputMemoryOffset() / 4;
    var outputMemoryOffset = tflite._getOutputMemoryOffset() / 4;
    var postProcessingConfig;
    function render() {
        return __awaiter(this, void 0, void 0, function () {
            return __generator(this, function (_a) {
                switch (_a.label) {
                    case 0:
                        if (backgroundConfig.type !== 'none') {
                            resizeSource();
                        }
                        addFrameEvent();
                        if (!(backgroundConfig.type !== 'none')) return [3 /*break*/, 3];
                        if (!(segmentationConfig.model === 'bodyPix')) return [3 /*break*/, 2];
                        return [4 /*yield*/, runBodyPixInference()];
                    case 1:
                        _a.sent();
                        return [3 /*break*/, 3];
                    case 2:
                        runTFLiteInference();
                        _a.label = 3;
                    case 3:
                        addFrameEvent();
                        runPostProcessing();
                        return [2 /*return*/];
                }
            });
        });
    }
    function updatePostProcessingConfig(newPostProcessingConfig) {
        postProcessingConfig = newPostProcessingConfig;
    }
    function cleanUp() {
        // Nothing to clean up in this rendering pipeline
    }
    function resizeSource() {
        segmentationMaskCtx.drawImage(sourcePlayback.htmlElement, 0, 0, sourcePlayback.width, sourcePlayback.height, 0, 0, segmentationWidth, segmentationHeight);
        if (segmentationConfig.model === 'meet' ||
            segmentationConfig.model === 'mlkit') {
            var imageData = segmentationMaskCtx.getImageData(0, 0, segmentationWidth, segmentationHeight);
            for (var i = 0; i < segmentationPixelCount; i++) {
                tflite.HEAPF32[inputMemoryOffset + i * 3] = imageData.data[i * 4] / 255;
                tflite.HEAPF32[inputMemoryOffset + i * 3 + 1] =
                    imageData.data[i * 4 + 1] / 255;
                tflite.HEAPF32[inputMemoryOffset + i * 3 + 2] =
                    imageData.data[i * 4 + 2] / 255;
            }
        }
    }
    function runBodyPixInference() {
        return __awaiter(this, void 0, void 0, function () {
            var segmentation, i;
            return __generator(this, function (_a) {
                switch (_a.label) {
                    case 0: return [4 /*yield*/, bodyPix.segmentPerson(segmentationMaskCanvas)];
                    case 1:
                        segmentation = _a.sent();
                        for (i = 0; i < segmentationPixelCount; i++) {
                            // Sets only the alpha component of each pixel
                            segmentationMask.data[i * 4 + 3] = segmentation.data[i] ? 255 : 0;
                        }
                        segmentationMaskCtx.putImageData(segmentationMask, 0, 0);
                        return [2 /*return*/];
                }
            });
        });
    }
    function runTFLiteInference() {
        tflite._runInference();
        for (var i = 0; i < segmentationPixelCount; i++) {
            if (segmentationConfig.model === 'meet') {
                var background = tflite.HEAPF32[outputMemoryOffset + i * 2];
                var person = tflite.HEAPF32[outputMemoryOffset + i * 2 + 1];
                var shift = Math.max(background, person);
                var backgroundExp = Math.exp(background - shift);
                var personExp = Math.exp(person - shift);
                // Sets only the alpha component of each pixel
                segmentationMask.data[i * 4 + 3] =
                    (255 * personExp) / (backgroundExp + personExp); // softmax
            }
            else if (segmentationConfig.model === 'mlkit') {
                var person = tflite.HEAPF32[outputMemoryOffset + i];
                segmentationMask.data[i * 4 + 3] = 255 * person;
            }
        }
        segmentationMaskCtx.putImageData(segmentationMask, 0, 0);
    }
    function runPostProcessing() {
        ctx.globalCompositeOperation = 'copy';
        ctx.filter = 'none';
        if (postProcessingConfig === null || postProcessingConfig === void 0 ? void 0 : postProcessingConfig.smoothSegmentationMask) {
            if (backgroundConfig.type === 'blur') {
                ctx.filter = 'blur(8px)'; // FIXME Does not work on Safari
            }
            else if (backgroundConfig.type === 'image') {
                ctx.filter = 'blur(4px)'; // FIXME Does not work on Safari
            }
        }
        if (backgroundConfig.type !== 'none') {
            drawSegmentationMask();
            ctx.globalCompositeOperation = 'source-in';
            ctx.filter = 'none';
        }
        ctx.drawImage(sourcePlayback.htmlElement, 0, 0);
        if (backgroundConfig.type === 'blur') {
            blurBackground();
        }
    }
    function drawSegmentationMask() {
        ctx.drawImage(segmentationMaskCanvas, 0, 0, segmentationWidth, segmentationHeight, 0, 0, sourcePlayback.width, sourcePlayback.height);
    }
    function blurBackground() {
        ctx.globalCompositeOperation = 'destination-over';
        ctx.filter = 'blur(8px)'; // FIXME Does not work on Safari
        ctx.drawImage(sourcePlayback.htmlElement, 0, 0);
    }
    return { render: render, updatePostProcessingConfig: updatePostProcessingConfig, cleanUp: cleanUp };
}
exports.buildCanvas2dPipeline = buildCanvas2dPipeline;
