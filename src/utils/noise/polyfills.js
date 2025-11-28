"use strict";
// https://github.com/MaxArt2501/base64-js/blob/master/base64.js
/* ============================================================================================== */
// base64 character set, plus padding character (=)
const b64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
// Regular expression to check formal correctness of base64 encoded strings
// eslint-disable-next-line no-useless-escape
const b64re = /^(?:[A-Za-z\d+\/]{4})*?(?:[A-Za-z\d+\/]{2}(?:==)?|[A-Za-z\d+\/]{3}=?)?$/;
// globalThis.btoa = function (string) {
//     string = String(string)
//     let bitmap,
//         a,
//         b,
//         c,
//         result = "",
//         i = 0,
//         rest = string.length % 3 // To determine the final padding
//     for (; i < string.length; ) {
//         if (
//             (a = string.charCodeAt(i++)) > 255 ||
//             (b = string.charCodeAt(i++)) > 255 ||
//             (c = string.charCodeAt(i++)) > 255
//         )
//             throw new TypeError(
//                 "Failed to execute 'btoa' on 'Window': The string to be encoded contains characters outside of the Latin1 range.",
//             )
//         bitmap = (a << 16) | (b << 8) | c
//         result +=
//             b64.charAt((bitmap >> 18) & 63) +
//             b64.charAt((bitmap >> 12) & 63) +
//             b64.charAt((bitmap >> 6) & 63) +
//             b64.charAt(bitmap & 63)
//     }
//     // If there's need of padding, replace the last 'A's with equal signs
//     return rest ? result.slice(0, rest - 3) + "===".substring(rest) : result
// }
globalThis.atob = function (string) {
    // atob can work with strings with whitespaces, even inside the encoded part,
    // but only \t, \n, \f, \r and ' ', which can be stripped.
    string = String(string).replace(/[\t\n\f\r ]+/g, "");
    if (!b64re.test(string))
        throw new TypeError("Failed to execute 'atob' on 'Window': The string to be decoded is not correctly encoded.");
    // Adding the padding if missing, for semplicity
    string += "==".slice(2 - (string.length & 3));
    let bitmap, result = "", r1, r2, i = 0;
    for (; i < string.length;) {
        bitmap =
            (b64.indexOf(string.charAt(i++)) << 18) |
                (b64.indexOf(string.charAt(i++)) << 12) |
                ((r1 = b64.indexOf(string.charAt(i++))) << 6) |
                (r2 = b64.indexOf(string.charAt(i++)));
        result +=
            r1 === 64
                ? String.fromCharCode((bitmap >> 16) & 255)
                : r2 === 64
                    ? String.fromCharCode((bitmap >> 16) & 255, (bitmap >> 8) & 255)
                    : String.fromCharCode((bitmap >> 16) & 255, (bitmap >> 8) & 255, bitmap & 255);
    }
    return result;
};
/* ============================================================================================== */
// @ts-expect-error does not exist in `AudioWorkletGlobalScope`
globalThis.self = { location: { href: "" } };
