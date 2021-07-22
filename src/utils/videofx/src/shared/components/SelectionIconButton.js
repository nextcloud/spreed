"use strict";
exports.__esModule = true;
var styles_1 = require("@material-ui/core/styles");
var SelectionButton_1 = require("./SelectionButton");
function SelectionIconButton(props) {
    var classes = useStyles();
    return (<SelectionButton_1["default"] active={props.active} onClick={props.onClick}>
      <div className={classes.root}>{props.children}</div>
    </SelectionButton_1["default"]>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    return styles_1.createStyles({
        root: {
            width: '100%',
            height: '100%',
            borderWidth: 1,
            borderStyle: 'solid',
            borderColor: 'rgba(0, 0, 0, 0.23)',
            borderRadius: theme.shape.borderRadius,
            margin: -1,
            boxSizing: 'content-box',
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center'
        }
    });
});
exports["default"] = SelectionIconButton;
