"use strict";
var __assign = (this && this.__assign) || function () {
    __assign = Object.assign || function(t) {
        for (var s, i = 1, n = arguments.length; i < n; i++) {
            s = arguments[i];
            for (var p in s) if (Object.prototype.hasOwnProperty.call(s, p))
                t[p] = s[p];
        }
        return t;
    };
    return __assign.apply(this, arguments);
};
exports.__esModule = true;
var styles_1 = require("@material-ui/core/styles");
var react_1 = require("react");
var BackgroundConfigCard_1 = require("./core/components/BackgroundConfigCard");
var PostProcessingConfigCard_1 = require("./core/components/PostProcessingConfigCard");
var SegmentationConfigCard_1 = require("./core/components/SegmentationConfigCard");
var SourceConfigCard_1 = require("./core/components/SourceConfigCard");
var ViewerCard_1 = require("./core/components/ViewerCard");
var backgroundHelper_1 = require("./core/helpers/backgroundHelper");
var sourceHelper_1 = require("./core/helpers/sourceHelper");
var useBodyPix_1 = require("./core/hooks/useBodyPix");
var useTFLite_1 = require("./core/hooks/useTFLite");
function App() {
    var classes = useStyles();
    var _a = react_1.useState({
        type: 'image',
        url: sourceHelper_1.sourceImageUrls[0]
    }), sourceConfig = _a[0], setSourceConfig = _a[1];
    var _b = react_1.useState({
        type: 'image',
        url: backgroundHelper_1.backgroundImageUrls[0]
    }), backgroundConfig = _b[0], setBackgroundConfig = _b[1];
    var _c = react_1.useState({
        model: 'meet',
        backend: 'wasm',
        inputResolution: '160x96',
        pipeline: 'webgl2'
    }), segmentationConfig = _c[0], setSegmentationConfig = _c[1];
    var _d = react_1.useState({
        smoothSegmentationMask: true,
        jointBilateralFilter: { sigmaSpace: 1, sigmaColor: 0.1 },
        coverage: [0.5, 0.75],
        lightWrapping: 0.3,
        blendMode: 'screen'
    }), postProcessingConfig = _d[0], setPostProcessingConfig = _d[1];
    var bodyPix = useBodyPix_1["default"]();
    var _e = useTFLite_1["default"](segmentationConfig), tflite = _e.tflite, isSIMDSupported = _e.isSIMDSupported;
    react_1.useEffect(function () {
        setSegmentationConfig(function (previousSegmentationConfig) {
            if (previousSegmentationConfig.backend === 'wasm' && isSIMDSupported) {
                return __assign(__assign({}, previousSegmentationConfig), { backend: 'wasmSimd' });
            }
            else {
                return previousSegmentationConfig;
            }
        });
    }, [isSIMDSupported]);
    return (<div className={classes.root}>
      <ViewerCard_1["default"] sourceConfig={sourceConfig} backgroundConfig={backgroundConfig} segmentationConfig={segmentationConfig} postProcessingConfig={postProcessingConfig} bodyPix={bodyPix} tflite={tflite}/>
      <SourceConfigCard_1["default"] config={sourceConfig} onChange={setSourceConfig}/>
      <BackgroundConfigCard_1["default"] config={backgroundConfig} onChange={setBackgroundConfig}/>
      <SegmentationConfigCard_1["default"] config={segmentationConfig} isSIMDSupported={isSIMDSupported} onChange={setSegmentationConfig}/>
      <PostProcessingConfigCard_1["default"] config={postProcessingConfig} pipeline={segmentationConfig.pipeline} onChange={setPostProcessingConfig}/>
    </div>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    var _a;
    return styles_1.createStyles({
        root: (_a = {
                display: 'grid'
            },
            _a[theme.breakpoints.up('xs')] = {
                margin: theme.spacing(1),
                gap: theme.spacing(1),
                gridTemplateColumns: '1fr'
            },
            _a[theme.breakpoints.up('md')] = {
                margin: theme.spacing(2),
                gap: theme.spacing(2),
                gridTemplateColumns: 'repeat(2, 1fr)'
            },
            _a[theme.breakpoints.up('lg')] = {
                gridTemplateColumns: 'repeat(3, 1fr)'
            },
            _a),
        resourceSelectionCards: {
            display: 'flex',
            flexDirection: 'column'
        }
    });
});
exports["default"] = App;
