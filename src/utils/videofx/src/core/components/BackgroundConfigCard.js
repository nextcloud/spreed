"use strict";
exports.__esModule = true;
var Card_1 = require("@material-ui/core/Card");
var CardContent_1 = require("@material-ui/core/CardContent");
var styles_1 = require("@material-ui/core/styles");
var Typography_1 = require("@material-ui/core/Typography");
var Block_1 = require("@material-ui/icons/Block");
var BlurOn_1 = require("@material-ui/icons/BlurOn");
var ImageButton_1 = require("../../shared/components/ImageButton");
var SelectionIconButton_1 = require("../../shared/components/SelectionIconButton");
var backgroundHelper_1 = require("../helpers/backgroundHelper");
function BackgroundConfigCard(props) {
    var classes = useStyles();
    return (<Card_1["default"] className={classes.root}>
      <CardContent_1["default"]>
        <Typography_1["default"] gutterBottom variant="h6" component="h2">
          Background
        </Typography_1["default"]>
        <SelectionIconButton_1["default"] active={props.config.type === 'none'} onClick={function () { return props.onChange({ type: 'none' }); }}>
          <Block_1["default"] />
        </SelectionIconButton_1["default"]>
        <SelectionIconButton_1["default"] active={props.config.type === 'blur'} onClick={function () { return props.onChange({ type: 'blur' }); }}>
          <BlurOn_1["default"] />
        </SelectionIconButton_1["default"]>
        {backgroundHelper_1.backgroundImageUrls.map(function (imageUrl) { return (<ImageButton_1["default"] key={imageUrl} imageUrl={imageUrl} active={imageUrl === props.config.url} onClick={function () { return props.onChange({ type: 'image', url: imageUrl }); }}/>); })}
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
exports["default"] = BackgroundConfigCard;
