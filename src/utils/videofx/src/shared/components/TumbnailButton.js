"use strict";
exports.__esModule = true;
var styles_1 = require("@material-ui/core/styles");
var Skeleton_1 = require("@material-ui/lab/Skeleton");
var clsx_1 = require("clsx");
var SelectionButton_1 = require("./SelectionButton");
function ThumbnailButton(props) {
    var classes = useStyles();
    return (<SelectionButton_1["default"] active={!!props.thumbnailUrl && props.active} disabled={!props.thumbnailUrl} onClick={props.onClick}>
      {props.thumbnailUrl ? (<img className={clsx_1["default"](classes.scalableContent, classes.image)} src={props.thumbnailUrl} alt="" onLoad={props.onLoad}/>) : (<Skeleton_1["default"] className={classes.scalableContent} variant="rect"/>)}
      {props.children}
    </SelectionButton_1["default"]>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    return styles_1.createStyles({
        scalableContent: {
            // Fixes rendering issues with border when scaled
            width: 'calc(100% + 2px)',
            height: 'calc(100% + 2px)',
            margin: -1,
            borderRadius: theme.shape.borderRadius
        },
        image: {
            objectFit: 'cover'
        }
    });
});
exports["default"] = ThumbnailButton;
