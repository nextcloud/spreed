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
var Card_1 = require("@material-ui/core/Card");
var CardContent_1 = require("@material-ui/core/CardContent");
var FormControl_1 = require("@material-ui/core/FormControl");
var InputLabel_1 = require("@material-ui/core/InputLabel");
var MenuItem_1 = require("@material-ui/core/MenuItem");
var Select_1 = require("@material-ui/core/Select");
var styles_1 = require("@material-ui/core/styles");
var Typography_1 = require("@material-ui/core/Typography");
function SegmentationConfigCard(props) {
    var classes = useStyles();
    function handleModelChange(event) {
        var model = event.target.value;
        var backend = props.config.backend;
        var inputResolution = props.config.inputResolution;
        var pipeline = props.config.pipeline;
        switch (model) {
            case 'bodyPix':
                backend = 'webgl';
                inputResolution = '640x360';
                pipeline = 'canvas2dCpu';
                break;
            case 'meet':
                if ((backend !== 'wasm' && backend !== 'wasmSimd') ||
                    (inputResolution !== '256x144' && inputResolution !== '160x96')) {
                    backend = props.isSIMDSupported ? 'wasmSimd' : 'wasm';
                    inputResolution = '160x96';
                    pipeline = 'webgl2';
                }
                break;
            case 'mlkit':
                if ((backend !== 'wasm' && backend !== 'wasmSimd') ||
                    inputResolution !== '256x256') {
                    backend = props.isSIMDSupported ? 'wasmSimd' : 'wasm';
                    inputResolution = '256x256';
                    pipeline = 'webgl2';
                }
                break;
        }
        props.onChange(__assign(__assign({}, props.config), { model: model, backend: backend, inputResolution: inputResolution, pipeline: pipeline }));
    }
    function handleBackendChange(event) {
        props.onChange(__assign(__assign({}, props.config), { backend: event.target.value }));
    }
    function handleInputResolutionChange(event) {
        props.onChange(__assign(__assign({}, props.config), { inputResolution: event.target.value }));
    }
    function handlePipelineChange(event) {
        props.onChange(__assign(__assign({}, props.config), { pipeline: event.target.value }));
    }
    return (<Card_1["default"] className={classes.root}>
      <CardContent_1["default"]>
        <Typography_1["default"] gutterBottom variant="h6" component="h2">
          Segmentation
        </Typography_1["default"]>
        <div className={classes.formControls}>
          <FormControl_1["default"] className={classes.formControl} variant="outlined">
            <InputLabel_1["default"]>Model</InputLabel_1["default"]>
            <Select_1["default"] label="Model" value={props.config.model} onChange={handleModelChange}>
              <MenuItem_1["default"] value="meet">Meet</MenuItem_1["default"]>
              <MenuItem_1["default"] value="mlkit">ML Kit</MenuItem_1["default"]>
              <MenuItem_1["default"] value="bodyPix">BodyPix</MenuItem_1["default"]>
            </Select_1["default"]>
          </FormControl_1["default"]>
          <FormControl_1["default"] className={classes.formControl} variant="outlined">
            <InputLabel_1["default"]>Backend</InputLabel_1["default"]>
            <Select_1["default"] label="Backend" value={props.config.backend} onChange={handleBackendChange}>
              <MenuItem_1["default"] value="wasm" disabled={props.config.model === 'bodyPix'}>
                WebAssembly
              </MenuItem_1["default"]>
              <MenuItem_1["default"] value="wasmSimd" disabled={props.config.model === 'bodyPix' || !props.isSIMDSupported}>
                WebAssembly SIMD
              </MenuItem_1["default"]>
              <MenuItem_1["default"] value="webgl" disabled={props.config.model !== 'bodyPix'}>
                WebGL
              </MenuItem_1["default"]>
            </Select_1["default"]>
          </FormControl_1["default"]>
          <FormControl_1["default"] className={classes.formControl} variant="outlined">
            <InputLabel_1["default"]>Input resolution</InputLabel_1["default"]>
            <Select_1["default"] label="Input resolution" value={props.config.inputResolution} onChange={handleInputResolutionChange}>
              <MenuItem_1["default"] value="640x360" disabled={props.config.model !== 'bodyPix'}>
                640x360
              </MenuItem_1["default"]>
              <MenuItem_1["default"] value="256x256" disabled={props.config.model !== 'mlkit'}>
                256x256
              </MenuItem_1["default"]>
              <MenuItem_1["default"] value="256x144" disabled={props.config.model !== 'meet'}>
                256x144
              </MenuItem_1["default"]>
              <MenuItem_1["default"] value="160x96" disabled={props.config.model !== 'meet'}>
                160x96
              </MenuItem_1["default"]>
            </Select_1["default"]>
          </FormControl_1["default"]>
          <FormControl_1["default"] className={classes.formControl} variant="outlined">
            <InputLabel_1["default"]>Pipeline</InputLabel_1["default"]>
            <Select_1["default"] label="Pipeline" value={props.config.pipeline} onChange={handlePipelineChange}>
              <MenuItem_1["default"] value="webgl2" disabled={props.config.model === 'bodyPix'}>
                WebGL 2
              </MenuItem_1["default"]>
              <MenuItem_1["default"] value="canvas2dCpu">Canvas 2D + CPU</MenuItem_1["default"]>
            </Select_1["default"]>
          </FormControl_1["default"]>
        </div>
      </CardContent_1["default"]>
    </Card_1["default"]>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    var _a;
    return styles_1.createStyles({
        root: (_a = {},
            _a[theme.breakpoints.only('md')] = {
                gridColumnStart: 2,
                gridRowStart: 2
            },
            _a),
        formControls: {
            display: 'flex',
            flexWrap: 'wrap'
        },
        formControl: {
            marginTop: theme.spacing(1),
            marginBottom: theme.spacing(1),
            marginRight: theme.spacing(2),
            minWidth: 200,
            flex: 1
        }
    });
});
exports["default"] = SegmentationConfigCard;
