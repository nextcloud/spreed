"use strict";
exports.__esModule = true;
exports.sourceVideoUrls = exports.sourceImageUrls = void 0;
exports.sourceImageUrls = [
    'girl-919048_1280',
    'doctor-5871743_640',
    'woman-5883428_1280',
].map(function (imageName) { return process.env.PUBLIC_URL + "/images/" + imageName + ".jpg"; });
exports.sourceVideoUrls = [
    'Dance - 32938',
    'Doctor - 26732',
    'Thoughtful - 35590',
].map(function (videoName) { return process.env.PUBLIC_URL + "/videos/" + videoName + ".mp4"; });
