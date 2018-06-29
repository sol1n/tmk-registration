var gulp = require('gulp'),
    less = require('gulp-less'),
    cssmin = require('gulp-cssmin'),
    rename = require('gulp-rename'),
    concat = require('gulp-concat'),
    uglify = require('gulp-uglify'),
    autoprefixer = require('gulp-autoprefixer'),
    sourcemaps = require('gulp-sourcemaps');

gulp.task('less', function() {
    return gulp.src('./resources/assets/less/style.less')
        .pipe(sourcemaps.init())
        .pipe(less().on('error', function(err) {
            console.log(err);
        }))
        .pipe(autoprefixer({
            browsers: ['last 2 versions'],
            cascade: false
        }))
        .pipe(cssmin().on('error', function(err) {
            console.log(err);
        }))
        .pipe(rename({
            suffix: '.min'
        }))
        .pipe(sourcemaps.write())
        .pipe(gulp.dest('./public/assets/build'));
});

var jsFiles = [
        './resources/assets/js/builded.js',
        './node_modules/trumbowyg/dist/trumbowyg.js',
        './node_modules/trumbowyg/dist/langs/ru.min.js',
        './resources/assets/js/additional.js'
    ],
    jsDest = './public/assets/build';

gulp.task('scripts', function() {
    return gulp.src(jsFiles)
        .pipe(sourcemaps.init())
        .pipe(concat('scripts.js'))
        .pipe(gulp.dest(jsDest))
        .pipe(rename('scripts.min.js'))
        .pipe(uglify())
        .pipe(sourcemaps.write())
        .pipe(gulp.dest(jsDest));
});

gulp.task('default', ['less', 'scripts'], function() {
    gulp.watch('./resources/assets/less/style.less', ['less']);
    gulp.watch('./resources/assets/less/**/*.less', ['less']);
    gulp.watch('./resources/assets/js/**/*.js', ['scripts']);
});
