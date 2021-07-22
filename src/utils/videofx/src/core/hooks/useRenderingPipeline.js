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
var react_1 = require("react");
var canvas2dPipeline_1 = require("../../pipelines/canvas2d/canvas2dPipeline");
var webgl2Pipeline_1 = require("../../pipelines/webgl2/webgl2Pipeline");
function useRenderingPipeline(sourcePlayback, backgroundConfig, segmentationConfig, bodyPix, tflite) {
    var _a = react_1.useState(null), pipeline = _a[0], setPipeline = _a[1];
    var backgroundImageRef = react_1.useRef(null);
    var canvasRef = react_1.useRef(null);
    var _b = react_1.useState(0), fps = _b[0], setFps = _b[1];
    var _c = react_1.useState([]), durations = _c[0], setDurations = _c[1];
    react_1.useEffect(function () {
        // The useEffect cleanup function is not enough to stop
        // the rendering loop when the framerate is low
        var shouldRender = true;
        var previousTime = 0;
        var beginTime = 0;
        var eventCount = 0;
        var frameCount = 0;
        var frameDurations = [];
        var renderRequestId;
        var newPipeline = segmentationConfig.pipeline === 'webgl2'
            ? webgl2Pipeline_1.buildWebGL2Pipeline(sourcePlayback, backgroundImageRef.current, backgroundConfig, segmentationConfig, canvasRef.current, tflite, addFrameEvent)
            : canvas2dPipeline_1.buildCanvas2dPipeline(sourcePlayback, backgroundConfig, segmentationConfig, canvasRef.current, bodyPix, tflite, addFrameEvent);
        function render() {
            return __awaiter(this, void 0, void 0, function () {
                return __generator(this, function (_a) {
                    switch (_a.label) {
                        case 0:
                            if (!shouldRender) {
                                return [2 /*return*/];
                            }
                            beginFrame();
                            return [4 /*yield*/, newPipeline.render()];
                        case 1:
                            _a.sent();
                            endFrame();
                            renderRequestId = requestAnimationFrame(render);
                            return [2 /*return*/];
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
                setFps((frameCount * 1000) / (time - previousTime));
                setDurations(frameDurations);
                previousTime = time;
                frameCount = 0;
            }
            eventCount = 0;
        }
        render();
        console.log('Animation started:', sourcePlayback, backgroundConfig, segmentationConfig);
        setPipeline(newPipeline);
        return function () {
            shouldRender = false;
            cancelAnimationFrame(renderRequestId);
            newPipeline.cleanUp();
            console.log('Animation stopped:', sourcePlayback, backgroundConfig, segmentationConfig);
            setPipeline(null);
        };
    }, [sourcePlayback, backgroundConfig, segmentationConfig, bodyPix, tflite]);
    return {
        pipeline: pipeline,
        backgroundImageRef: backgroundImageRef,
        canvasRef: canvasRef,
        fps: fps,
        durations: durations
    };
}
exports["default"] = useRenderingPipeline;
