"use strict";
exports.__esModule = true;
var styles_1 = require("@material-ui/core/styles");
var Typography_1 = require("@material-ui/core/Typography");
var react_1 = require("react");
var useRenderingPipeline_1 = require("../hooks/useRenderingPipeline");
function OutputViewer(props) {
    var classes = useStyles();
    var _a = useRenderingPipeline_1["default"](props.sourcePlayback, props.backgroundConfig, props.segmentationConfig, props.bodyPix, props.tflite), pipeline = _a.pipeline, backgroundImageRef = _a.backgroundImageRef, canvasRef = _a.canvasRef, fps = _a.fps, _b = _a.durations, resizingDuration = _b[0], inferenceDuration = _b[1], postProcessingDuration = _b[2];
    react_1.useEffect(function () {
        if (pipeline) {
            pipeline.updatePostProcessingConfig(props.postProcessingConfig);
        }
    }, [pipeline, props.postProcessingConfig]);
    var statDetails = [
        "resizing " + resizingDuration + "ms",
        "inference " + inferenceDuration + "ms",
        "post-processing " + postProcessingDuration + "ms",
    ];
    var stats = Math.round(fps) + " fps (" + statDetails.join(', ') + ")";
    return (<div className={classes.root}>
      {props.backgroundConfig.type === 'image' && (<img ref={backgroundImageRef} className={classes.render} src={props.backgroundConfig.url} alt="" hidden={props.segmentationConfig.pipeline === 'webgl2'}/>)}
      <canvas 
    // The key attribute is required to create a new canvas when switching
    // context mode
    key={props.segmentationConfig.pipeline} ref={canvasRef} className={classes.render} width={props.sourcePlayback.width} height={props.sourcePlayback.height}/>
      <Typography_1["default"] className={classes.stats} variant="caption">
        {stats}
      </Typography_1["default"]>
    </div>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    return styles_1.createStyles({
        root: {
            flex: 1,
            position: 'relative'
        },
        render: {
            position: 'absolute',
            width: '100%',
            height: '100%',
            objectFit: 'cover'
        },
        stats: {
            position: 'absolute',
            top: 0,
            right: 0,
            left: 0,
            textAlign: 'center',
            backgroundColor: 'rgba(0, 0, 0, 0.48)',
            color: theme.palette.common.white
        }
    });
});
exports["default"] = OutputViewer;
