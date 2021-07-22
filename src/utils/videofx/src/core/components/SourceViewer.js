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
var CircularProgress_1 = require("@material-ui/core/CircularProgress");
var styles_1 = require("@material-ui/core/styles");
var VideocamOff_1 = require("@material-ui/icons/VideocamOff");
var react_1 = require("react");
function SourceViewer(props) {
    var classes = useStyles();
    var _a = react_1.useState(), sourceUrl = _a[0], setSourceUrl = _a[1];
    var _b = react_1.useState(false), isLoading = _b[0], setLoading = _b[1];
    var _c = react_1.useState(false), isCameraError = _c[0], setCameraError = _c[1];
    var videoRef = react_1.useRef(null);
    react_1.useEffect(function () {
        setSourceUrl(undefined);
        setLoading(true);
        setCameraError(false);
        // Enforces reloading the resource, otherwise
        // onLoad event is not always dispatched and the
        // progress indicator never disappears
        setTimeout(function () { return setSourceUrl(props.sourceConfig.url); });
    }, [props.sourceConfig]);
    react_1.useEffect(function () {
        function getCameraStream() {
            return __awaiter(this, void 0, void 0, function () {
                var constraint, stream, error_1;
                return __generator(this, function (_a) {
                    switch (_a.label) {
                        case 0:
                            _a.trys.push([0, 2, , 3]);
                            constraint = { video: true };
                            return [4 /*yield*/, navigator.mediaDevices.getUserMedia(constraint)];
                        case 1:
                            stream = _a.sent();
                            if (videoRef.current) {
                                videoRef.current.srcObject = stream;
                                return [2 /*return*/];
                            }
                            return [3 /*break*/, 3];
                        case 2:
                            error_1 = _a.sent();
                            console.error('Error opening video camera.', error_1);
                            return [3 /*break*/, 3];
                        case 3:
                            setLoading(false);
                            setCameraError(true);
                            return [2 /*return*/];
                    }
                });
            });
        }
        if (props.sourceConfig.type === 'camera') {
            getCameraStream();
        }
        else if (videoRef.current) {
            videoRef.current.srcObject = null;
        }
    }, [props.sourceConfig]);
    function handleImageLoad(event) {
        var image = event.target;
        props.onLoad({
            htmlElement: image,
            width: image.naturalWidth,
            height: image.naturalHeight
        });
        setLoading(false);
    }
    function handleVideoLoad(event) {
        var video = event.target;
        props.onLoad({
            htmlElement: video,
            width: video.videoWidth,
            height: video.videoHeight
        });
        setLoading(false);
    }
    return (<div className={classes.root}>
      {isLoading && <CircularProgress_1["default"] />}
      {props.sourceConfig.type === 'image' ? (<img className={classes.sourcePlayback} src={sourceUrl} hidden={isLoading} alt="" onLoad={handleImageLoad}/>) : isCameraError ? (<VideocamOff_1["default"] fontSize="large"/>) : (<video ref={videoRef} className={classes.sourcePlayback} src={sourceUrl} hidden={isLoading} autoPlay playsInline controls={false} muted loop onLoadedData={handleVideoLoad}/>)}
    </div>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    var _a;
    return styles_1.createStyles({
        root: (_a = {
                position: 'relative',
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center'
            },
            _a[theme.breakpoints.down('xs')] = {
                width: 0,
                overflow: 'hidden'
            },
            _a[theme.breakpoints.up('sm')] = {
                flex: 1,
                borderRightWidth: 1,
                borderRightStyle: 'solid',
                borderRightColor: theme.palette.divider
            },
            _a),
        sourcePlayback: {
            position: 'absolute',
            width: '100%',
            height: '100%',
            objectFit: 'cover'
        }
    });
});
exports["default"] = SourceViewer;
