"use strict";
exports.__esModule = true;
var styles_1 = require("@material-ui/core/styles");
var PlayCircleOutline_1 = require("@material-ui/icons/PlayCircleOutline");
var useVideoThumbnail_1 = require("../hooks/useVideoThumbnail");
var TumbnailButton_1 = require("./TumbnailButton");
function VideoButton(props) {
    var classes = useStyles();
    var _a = useVideoThumbnail_1["default"](props.videoUrl), thumbnailUrl = _a[0], revokeThumbnailUrl = _a[1];
    return (<TumbnailButton_1["default"] thumbnailUrl={thumbnailUrl} active={props.active} onClick={props.onClick} onLoad={revokeThumbnailUrl}>
      <PlayCircleOutline_1["default"] className={classes.icon}/>
    </TumbnailButton_1["default"]>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    return styles_1.createStyles({
        icon: {
            position: 'absolute',
            bottom: 0,
            right: 0,
            color: theme.palette.common.white
        }
    });
});
exports["default"] = VideoButton;
