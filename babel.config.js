const babelConfig = require('@nextcloud/babel-config')

module.exports = babelConfig

// Config for jest
module.exports.presets.push('@babel/preset-typescript')
