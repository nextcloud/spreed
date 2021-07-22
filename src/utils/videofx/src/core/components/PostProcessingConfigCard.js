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
var FormControlLabel_1 = require("@material-ui/core/FormControlLabel");
var InputLabel_1 = require("@material-ui/core/InputLabel");
var MenuItem_1 = require("@material-ui/core/MenuItem");
var Select_1 = require("@material-ui/core/Select");
var Slider_1 = require("@material-ui/core/Slider");
var styles_1 = require("@material-ui/core/styles");
var Switch_1 = require("@material-ui/core/Switch");
var Typography_1 = require("@material-ui/core/Typography");
var react_1 = require("react");
function PostProcessingConfigCard(props) {
    var classes = useStyles();
    function handleSmoothSegmentationMaskChange(event) {
        props.onChange(__assign(__assign({}, props.config), { smoothSegmentationMask: event.target.checked }));
    }
    function handleSigmaSpaceChange(_event, value) {
        props.onChange(__assign(__assign({}, props.config), { jointBilateralFilter: __assign(__assign({}, props.config.jointBilateralFilter), { sigmaSpace: value }) }));
    }
    function handleSigmaColorChange(_event, value) {
        props.onChange(__assign(__assign({}, props.config), { jointBilateralFilter: __assign(__assign({}, props.config.jointBilateralFilter), { sigmaColor: value }) }));
    }
    function handleCoverageChange(_event, value) {
        props.onChange(__assign(__assign({}, props.config), { coverage: value }));
    }
    function handleLightWrappingChange(_event, value) {
        props.onChange(__assign(__assign({}, props.config), { lightWrapping: value }));
    }
    function handleBlendModeChange(event) {
        props.onChange(__assign(__assign({}, props.config), { blendMode: event.target.value }));
    }
    return (<Card_1["default"]>
      <CardContent_1["default"]>
        <Typography_1["default"] gutterBottom variant="h6" component="h2">
          Post-processing
        </Typography_1["default"]>
        {props.pipeline === 'webgl2' ? (<react_1["default"].Fragment>
            <Typography_1["default"] gutterBottom>Joint bilateral filter</Typography_1["default"]>
            <Typography_1["default"] variant="body2">Sigma space</Typography_1["default"]>
            <Slider_1["default"] value={props.config.jointBilateralFilter.sigmaSpace} min={0} max={10} step={0.1} valueLabelDisplay="auto" onChange={handleSigmaSpaceChange}/>
            <Typography_1["default"] variant="body2">Sigma color</Typography_1["default"]>
            <Slider_1["default"] value={props.config.jointBilateralFilter.sigmaColor} min={0} max={1} step={0.01} valueLabelDisplay="auto" onChange={handleSigmaColorChange}/>
            <Typography_1["default"] gutterBottom>Background</Typography_1["default"]>
            <Typography_1["default"] variant="body2">Coverage</Typography_1["default"]>
            <Slider_1["default"] value={props.config.coverage} min={0} max={1} step={0.01} valueLabelDisplay="auto" onChange={handleCoverageChange}/>
            <Typography_1["default"] variant="body2" gutterBottom>
              Light wrapping
            </Typography_1["default"]>
            <div className={classes.lightWrapping}>
              <FormControl_1["default"] className={classes.formControl} variant="outlined">
                <InputLabel_1["default"]>Blend mode</InputLabel_1["default"]>
                <Select_1["default"] label="Blend mode" value={props.config.blendMode} onChange={handleBlendModeChange}>
                  <MenuItem_1["default"] value="screen">Screen</MenuItem_1["default"]>
                  <MenuItem_1["default"] value="linearDodge">Linear dodge</MenuItem_1["default"]>
                </Select_1["default"]>
              </FormControl_1["default"]>
              <Slider_1["default"] value={props.config.lightWrapping} min={0} max={1} step={0.01} valueLabelDisplay="auto" onChange={handleLightWrappingChange}/>
            </div>
          </react_1["default"].Fragment>) : (<FormControlLabel_1["default"] label="Smooth segmentation mask" control={<Switch_1["default"] color="primary" checked={props.config.smoothSegmentationMask} onChange={handleSmoothSegmentationMaskChange}/>}/>)}
      </CardContent_1["default"]>
    </Card_1["default"]>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    return styles_1.createStyles({
        lightWrapping: {
            display: 'flex',
            alignItems: 'center'
        },
        formControl: {
            marginTop: theme.spacing(2),
            marginBottom: theme.spacing(1),
            marginRight: theme.spacing(2),
            minWidth: 160
        }
    });
});
exports["default"] = PostProcessingConfigCard;
