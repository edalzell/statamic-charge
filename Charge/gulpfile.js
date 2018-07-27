let elixir = require('laravel-elixir');
let glob = require('glob');
let zip = require('gulp-zip');

require('laravel-elixir-vue');

elixir(function (mix) {
    mix.webpack(glob.sync('./resources/assets/js/components/*.{vue,js}'), './resources/assets/js/scripts.js');
});

gulp.task('release', function() {
   return gulp.src(['../**/*', '!./node_modules/', '!./node_modules/**', '!../.git', '!../.idea', '!./*.zip', '!../.vscode'])
       .pipe(zip('charge.zip'))
       .pipe(gulp.dest('../'));
});