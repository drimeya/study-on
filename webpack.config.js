// webpack.config.js
const Encore = require('@symfony/webpack-encore');

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')

    .addEntry('app', './assets/app.js')

    .enableSassLoader()
    
    // Enable single runtime chunk
    .enableSingleRuntimeChunk()

    // uncomment this if you want use jQuery in the following example
    //.autoProvidejQuery()
;

module.exports = Encore.getWebpackConfig();