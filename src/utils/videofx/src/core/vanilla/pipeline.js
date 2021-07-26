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
var webgl2Pipeline_1 = require("../../pipelines/webgl2/webgl2Pipeline");
function pipeline(video, canvasOutput, backgroundConfig, segmentationConfig, tflite) {
    var fps = 0;
    var durations = [];
    var previousTime = 0;
    var beginTime = 0;
    var eventCount = 0;
    var frameCount = 0;
    var frameDurations = [];
    // let interval: any;
    var renderRequestId;
    var webglPipeline = webgl2Pipeline_1.buildWebGL2Pipeline(video, backgroundConfig, segmentationConfig, canvasOutput, tflite, addFrameEvent);
    webglPipeline.updatePostProcessingConfig({
        smoothSegmentationMask: true,
        jointBilateralFilter: { sigmaSpace: 1, sigmaColor: 0.1 },
        coverage: [0.5, 0.75],
        lightWrapping: 0.3,
        blendMode: 'screen'
    });
    function render() {
        return __awaiter(this, void 0, void 0, function () {
            var error_1;
            return __generator(this, function (_a) {
                switch (_a.label) {
                    case 0:
                        beginFrame();
                        _a.label = 1;
                    case 1:
                        _a.trys.push([1, 5, , 6]);
                        if (!!window.stopBlur) return [3 /*break*/, 3];
                        return [4 /*yield*/, webglPipeline.render()];
                    case 2:
                        _a.sent();
                        endFrame();
                        renderRequestId = requestAnimationFrame(render);
                        return [3 /*break*/, 4];
                    case 3:
                        console.log('Animation stopped');
                        _a.label = 4;
                    case 4: return [3 /*break*/, 6];
                    case 5:
                        error_1 = _a.sent();
                        if (renderRequestId)
                            cancelAnimationFrame(renderRequestId);
                        webglPipeline.cleanUp();
                        throw error_1;
                    case 6: return [2 /*return*/];
                }
            });
        });
    }
    function beginFrame() {
        beginTime = Date.now();
    }
    function addFrameEvent() {
        var time = Date.now();
        frameDurations[eventCount] = time - beginTime;
        beginTime = time;
        eventCount++;
    }
    function endFrame() {
        var time = Date.now();
        frameDurations[eventCount] = time - beginTime;
        frameCount++;
        if (time >= previousTime + 1000) {
            fps = (frameCount * 1000) / (time - previousTime);
            durations = frameDurations;
            previousTime = time;
            frameCount = 0;
        }
        eventCount = 0;
    }
    render();
    // interval = setInterval(() => {
    //   renderRequestId = requestAnimationFrame(render)
    // }, 1000 / 30)
    return {
        webglPipeline: webglPipeline,
        canvasOutput: canvasOutput,
        fps: fps,
        durations: durations
    };
}
exports["default"] = pipeline;
