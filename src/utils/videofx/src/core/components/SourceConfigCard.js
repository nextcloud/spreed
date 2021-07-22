"use strict";
exports.__esModule = true;
var Card_1 = require("@material-ui/core/Card");
var CardContent_1 = require("@material-ui/core/CardContent");
var styles_1 = require("@material-ui/core/styles");
var Typography_1 = require("@material-ui/core/Typography");
var Videocam_1 = require("@material-ui/icons/Videocam");
var ImageButton_1 = require("../../shared/components/ImageButton");
var SelectionIconButton_1 = require("../../shared/components/SelectionIconButton");
var VideoButton_1 = require("../../shared/components/VideoButton");
var sourceHelper_1 = require("../helpers/sourceHelper");
function SourceConfigCard(props) {
    var classes = useStyles();
    return (<Card_1["default"] className={classes.root}>
      <CardContent_1["default"]>
        <Typography_1["default"] gutterBottom variant="h6" component="h2">
          Source
        </Typography_1["default"]>
        <SelectionIconButton_1["default"] active={props.config.type === 'camera'} onClick={function () { return props.onChange({ type: 'camera' }); }}>
          <Videocam_1["default"] />
        </SelectionIconButton_1["default"]>
        {sourceHelper_1.sourceImageUrls.map(function (imageUrl) { return (<ImageButton_1["default"] key={imageUrl} imageUrl={imageUrl} active={imageUrl === props.config.url} onClick={function () { return props.onChange({ type: 'image', url: imageUrl }); }}/>); })}
        {sourceHelper_1.sourceVideoUrls.map(function (videoUrl) { return (<VideoButton_1["default"] key={videoUrl} videoUrl={videoUrl} active={videoUrl === props.config.url} onClick={function () { return props.onChange({ type: 'video', url: videoUrl }); }}/>); })}
      </CardContent_1["default"]>
    </Card_1["default"]>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    return styles_1.createStyles({
        root: {
            flex: 1
        }
    });
});
exports["default"] = SourceConfigCard;
