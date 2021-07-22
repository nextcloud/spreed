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
exports.readPixelsAsync = exports.createTexture = exports.compileShader = exports.createProgram = exports.createPiplelineStageProgram = exports.glsl = void 0;
/**
 * Use it along with boyswan.glsl-literal VSCode extension
 * to get GLSL syntax highlighting.
 * https://marketplace.visualstudio.com/items?itemName=boyswan.glsl-literal
 *
 * On VSCode OSS, boyswan.glsl-literal requires slevesque.shader extension
 * to be installed as well.
 * https://marketplace.visualstudio.com/items?itemName=slevesque.shader
 */
exports.glsl = String.raw;
function createPiplelineStageProgram(gl, vertexShader, fragmentShader, positionBuffer, texCoordBuffer) {
    var program = createProgram(gl, vertexShader, fragmentShader);
    var positionAttributeLocation = gl.getAttribLocation(program, 'a_position');
    gl.enableVertexAttribArray(positionAttributeLocation);
    gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
    gl.vertexAttribPointer(positionAttributeLocation, 2, gl.FLOAT, false, 0, 0);
    var texCoordAttributeLocation = gl.getAttribLocation(program, 'a_texCoord');
    gl.enableVertexAttribArray(texCoordAttributeLocation);
    gl.bindBuffer(gl.ARRAY_BUFFER, texCoordBuffer);
    gl.vertexAttribPointer(texCoordAttributeLocation, 2, gl.FLOAT, false, 0, 0);
    return program;
}
exports.createPiplelineStageProgram = createPiplelineStageProgram;
function createProgram(gl, vertexShader, fragmentShader) {
    var program = gl.createProgram();
    gl.attachShader(program, vertexShader);
    gl.attachShader(program, fragmentShader);
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        throw new Error("Could not link WebGL program: " + gl.getProgramInfoLog(program));
    }
    return program;
}
exports.createProgram = createProgram;
function compileShader(gl, shaderType, shaderSource) {
    var shader = gl.createShader(shaderType);
    gl.shaderSource(shader, shaderSource);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
        throw new Error("Could not compile shader: " + gl.getShaderInfoLog(shader));
    }
    return shader;
}
exports.compileShader = compileShader;
function createTexture(gl, internalformat, width, height, minFilter, magFilter) {
    if (minFilter === void 0) { minFilter = gl.NEAREST; }
    if (magFilter === void 0) { magFilter = gl.NEAREST; }
    var texture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, texture);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, minFilter);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, magFilter);
    gl.texStorage2D(gl.TEXTURE_2D, 1, internalformat, width, height);
    return texture;
}
exports.createTexture = createTexture;
function readPixelsAsync(gl, x, y, width, height, format, type, dest) {
    return __awaiter(this, void 0, void 0, function () {
        var buf;
        return __generator(this, function (_a) {
            switch (_a.label) {
                case 0:
                    buf = gl.createBuffer();
                    gl.bindBuffer(gl.PIXEL_PACK_BUFFER, buf);
                    gl.bufferData(gl.PIXEL_PACK_BUFFER, dest.byteLength, gl.STREAM_READ);
                    gl.readPixels(x, y, width, height, format, type, 0);
                    gl.bindBuffer(gl.PIXEL_PACK_BUFFER, null);
                    return [4 /*yield*/, getBufferSubDataAsync(gl, gl.PIXEL_PACK_BUFFER, buf, 0, dest)];
                case 1:
                    _a.sent();
                    gl.deleteBuffer(buf);
                    return [2 /*return*/, dest];
            }
        });
    });
}
exports.readPixelsAsync = readPixelsAsync;
function getBufferSubDataAsync(gl, target, buffer, srcByteOffset, dstBuffer, dstOffset, length) {
    return __awaiter(this, void 0, void 0, function () {
        var sync, res;
        return __generator(this, function (_a) {
            switch (_a.label) {
                case 0:
                    sync = gl.fenceSync(gl.SYNC_GPU_COMMANDS_COMPLETE, 0);
                    gl.flush();
                    return [4 /*yield*/, clientWaitAsync(gl, sync)];
                case 1:
                    res = _a.sent();
                    gl.deleteSync(sync);
                    if (res !== gl.WAIT_FAILED) {
                        gl.bindBuffer(target, buffer);
                        gl.getBufferSubData(target, srcByteOffset, dstBuffer, dstOffset, length);
                        gl.bindBuffer(target, null);
                    }
                    return [2 /*return*/];
            }
        });
    });
}
function clientWaitAsync(gl, sync) {
    return new Promise(function (resolve) {
        function test() {
            var res = gl.clientWaitSync(sync, 0, 0);
            if (res === gl.WAIT_FAILED) {
                resolve(res);
                return;
            }
            if (res === gl.TIMEOUT_EXPIRED) {
                requestAnimationFrame(test);
                return;
            }
            resolve(res);
        }
        requestAnimationFrame(test);
    });
}
