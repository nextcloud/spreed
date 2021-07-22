"use strict";
exports.__esModule = true;
var Avatar_1 = require("@material-ui/core/Avatar");
var Paper_1 = require("@material-ui/core/Paper");
var styles_1 = require("@material-ui/core/styles");
var react_1 = require("react");
var OutputViewer_1 = require("./OutputViewer");
var SourceViewer_1 = require("./SourceViewer");
function ViewerCard(props) {
    var classes = useStyles();
    var _a = react_1.useState(), sourcePlayback = _a[0], setSourcePlayback = _a[1];
    react_1.useEffect(function () {
        setSourcePlayback(undefined);
    }, [props.sourceConfig]);
    return (<Paper_1["default"] className={classes.root}>
      <SourceViewer_1["default"] sourceConfig={props.sourceConfig} onLoad={setSourcePlayback}/>
      {sourcePlayback && props.bodyPix && props.tflite ? (<OutputViewer_1["default"] sourcePlayback={sourcePlayback} backgroundConfig={props.backgroundConfig} segmentationConfig={props.segmentationConfig} postProcessingConfig={props.postProcessingConfig} bodyPix={props.bodyPix} tflite={props.tflite}/>) : (<div className={classes.noOutput}>
          <Avatar_1["default"] className={classes.avatar}/>
        </div>)}
    </Paper_1["default"]>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    var _a;
    var minHeight = [theme.spacing(52) + "px", "100vh - " + theme.spacing(2) + "px"];
    return styles_1.createStyles({
        root: (_a = {
                minHeight: "calc(min(" + minHeight.join(', ') + "))",
                display: 'flex',
                overflow: 'hidden'
            },
            _a[theme.breakpoints.up('md')] = {
                gridColumnStart: 1,
                gridColumnEnd: 3
            },
            _a[theme.breakpoints.up('lg')] = {
                gridRowStart: 1,
                gridRowEnd: 3
            },
            _a),
        noOutput: {
            flex: 1,
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center'
        },
        avatar: {
            width: theme.spacing(20),
            height: theme.spacing(20)
        }
    });
});
exports["default"] = ViewerCard;
