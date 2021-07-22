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
var segmentationHelper_1 = require("../helpers/segmentationHelper");
function useTFLite(segmentationConfig) {
    var _a = react_1.useState(), tflite = _a[0], setTFLite = _a[1];
    var _b = react_1.useState(), tfliteSIMD = _b[0], setTFLiteSIMD = _b[1];
    var _c = react_1.useState(), selectedTFLite = _c[0], setSelectedTFLite = _c[1];
    var _d = react_1.useState(false), isSIMDSupported = _d[0], setSIMDSupported = _d[1];
    react_1.useEffect(function () {
        function loadTFLite() {
            return __awaiter(this, void 0, void 0, function () {
                var createdTFLiteSIMD, error_1;
                return __generator(this, function (_a) {
                    switch (_a.label) {
                        case 0:
                            createTFLiteModule().then(setTFLite);
                            _a.label = 1;
                        case 1:
                            _a.trys.push([1, 3, , 4]);
                            return [4 /*yield*/, createTFLiteSIMDModule()];
                        case 2:
                            createdTFLiteSIMD = _a.sent();
                            setTFLiteSIMD(createdTFLiteSIMD);
                            setSIMDSupported(true);
                            return [3 /*break*/, 4];
                        case 3:
                            error_1 = _a.sent();
                            console.warn('Failed to create TFLite SIMD WebAssembly module.', error_1);
                            return [3 /*break*/, 4];
                        case 4: return [2 /*return*/];
                    }
                });
            });
        }
        loadTFLite();
    }, []);
    react_1.useEffect(function () {
        function loadTFLiteModel() {
            return __awaiter(this, void 0, void 0, function () {
                var newSelectedTFLite, modelFileName, modelResponse, model, modelBufferOffset;
                return __generator(this, function (_a) {
                    switch (_a.label) {
                        case 0:
                            if (!tflite ||
                                (isSIMDSupported && !tfliteSIMD) ||
                                (!isSIMDSupported && segmentationConfig.backend === 'wasmSimd') ||
                                (segmentationConfig.model !== 'meet' &&
                                    segmentationConfig.model !== 'mlkit')) {
                                return [2 /*return*/];
                            }
                            setSelectedTFLite(undefined);
                            newSelectedTFLite = segmentationConfig.backend === 'wasmSimd' ? tfliteSIMD : tflite;
                            if (!newSelectedTFLite) {
                                throw new Error("TFLite backend unavailable: " + segmentationConfig.backend);
                            }
                            modelFileName = segmentationHelper_1.getTFLiteModelFileName(segmentationConfig.model, segmentationConfig.inputResolution);
                            console.log('Loading tflite model:', modelFileName);
                            return [4 /*yield*/, fetch(process.env.PUBLIC_URL + "/models/" + modelFileName + ".tflite")];
                        case 1:
                            modelResponse = _a.sent();
                            return [4 /*yield*/, modelResponse.arrayBuffer()];
                        case 2:
                            model = _a.sent();
                            console.log('Model buffer size:', model.byteLength);
                            modelBufferOffset = newSelectedTFLite._getModelBufferMemoryOffset();
                            console.log('Model buffer memory offset:', modelBufferOffset);
                            console.log('Loading model buffer...');
                            newSelectedTFLite.HEAPU8.set(new Uint8Array(model), modelBufferOffset);
                            console.log('_loadModel result:', newSelectedTFLite._loadModel(model.byteLength));
                            console.log('Input memory offset:', newSelectedTFLite._getInputMemoryOffset());
                            console.log('Input height:', newSelectedTFLite._getInputHeight());
                            console.log('Input width:', newSelectedTFLite._getInputWidth());
                            console.log('Input channels:', newSelectedTFLite._getInputChannelCount());
                            console.log('Output memory offset:', newSelectedTFLite._getOutputMemoryOffset());
                            console.log('Output height:', newSelectedTFLite._getOutputHeight());
                            console.log('Output width:', newSelectedTFLite._getOutputWidth());
                            console.log('Output channels:', newSelectedTFLite._getOutputChannelCount());
                            setSelectedTFLite(newSelectedTFLite);
                            return [2 /*return*/];
                    }
                });
            });
        }
        loadTFLiteModel();
    }, [
        tflite,
        tfliteSIMD,
        isSIMDSupported,
        segmentationConfig.model,
        segmentationConfig.backend,
        segmentationConfig.inputResolution,
    ]);
    return { tflite: selectedTFLite, isSIMDSupported: isSIMDSupported };
}
exports["default"] = useTFLite;
