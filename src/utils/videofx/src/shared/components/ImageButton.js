"use strict";
exports.__esModule = true;
var useImageThumbnail_1 = require("../hooks/useImageThumbnail");
var TumbnailButton_1 = require("./TumbnailButton");
function ImageButton(props) {
    var _a = useImageThumbnail_1["default"](props.imageUrl), thumbnailUrl = _a[0], revokeThumbnailUrl = _a[1];
    return (<TumbnailButton_1["default"] thumbnailUrl={thumbnailUrl} active={props.active} onClick={props.onClick} onLoad={revokeThumbnailUrl}/>);
}
exports["default"] = ImageButton;
