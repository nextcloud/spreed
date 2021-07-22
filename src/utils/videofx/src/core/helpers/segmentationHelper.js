"use strict";
exports.__esModule = true;
exports.getTFLiteModelFileName = exports.inputResolutions = void 0;
exports.inputResolutions = {
    '256x144': [256, 144],
    '160x96': [160, 96]
};
function getTFLiteModelFileName(model, inputResolution) {
    switch (model) {
        case 'meet':
            return inputResolution === '256x144' ? 'segm_full_v679' : 'segm_lite_v681';
        default:
            throw new Error("No TFLite file for this segmentation model: " + model);
    }
}
exports.getTFLiteModelFileName = getTFLiteModelFileName;
