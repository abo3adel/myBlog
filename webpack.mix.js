const mix = require("laravel-mix");

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.browserSync({
    proxy: 'http://bl.test',
    open: false
});

mix.js("resources/js/app.js", "public/js")
    .ts("resources/js/ts.ts", "public/js")
    .sass("resources/sass/app.scss", "public/css")
    .version();
