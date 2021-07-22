"use strict";
exports.__esModule = true;
var Button_1 = require("@material-ui/core/Button");
var styles_1 = require("@material-ui/core/styles");
var clsx_1 = require("clsx");
function SelectionButton(props) {
    var classes = useStyles();
    return (<Button_1["default"] className={clsx_1["default"](classes.root, props.active && classes.active)} disabled={props.disabled} onClick={props.onClick}>
      {props.children}
    </Button_1["default"]>);
}
var useStyles = styles_1.makeStyles(function (theme) {
    return styles_1.createStyles({
        root: {
            padding: 0,
            minWidth: theme.spacing(7) + 2,
            height: theme.spacing(7) + 2,
            width: theme.spacing(7) + 2,
            marginRight: theme.spacing(1),
            marginBottom: theme.spacing(1),
            border: '2px solid transparent',
            alignItems: 'stretch',
            transitionProperty: 'transform, border-color',
            transitionDuration: theme.transitions.duration.shorter + "ms",
            transitionTimingFunction: theme.transitions.easing.easeInOut,
            '&:hover': {
                transform: 'scale(1.125)'
            }
        },
        active: {
            borderColor: theme.palette.primary.main,
            transform: 'scale(1.125)'
        }
    });
});
exports["default"] = SelectionButton;
