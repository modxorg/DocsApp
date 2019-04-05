# Frontend Tooling

Our frontend workflow includes NPM Scripts, SASS and Webpack. We use [namespaced BEM](https://csswizardry.com/2015/03/more-transparent-ui-code-with-namespaces/) for classnames in CSS and [ES6 syntax](https://www.taniarascia.com/es6-syntax-and-feature-overview/) for JavaScript.

## Prerequisites

Make sure you got *node* (version 10.12.x or higher) and *npm* isntalled. You can test this with:
```
node -v
```
(should output something like `v10.15.0`)
```
npm -v
```
(should output something like `6.9.0`)

## Initialization

To download and install all dependencies you need to `cd` into the `template` folder and run `npm install`.
```
cd /public/template/
npm install
```
Whenever new dependencies get added to the project, you need to run this again.

## Building assets

We use [npm scripts](https://css-tricks.com/why-npm-scripts/) for building our frontend assets (instead of Grunt or Gulp). Those scripts are defined in the [package.json](package.json) file in the `scripts` section.

### Start development

Normally when starting your frontend work, you want to start livereload and automatically watch for file changes (and re-build assets automatically). This can be done with the `start` command that triggers a couple of processes.
```
npm run start
```
(or short `npm start`)

### Generating dev builds

When you just want to create a single dev build of the assets, you can simply run one of the following commands:
```
npm run build:js
```
```
npm run build:css
```
```
npm run build:svg
```
Or to build all 3 types together simply:
```
npm run build
```

### Prepare a new release
To build CSS, JS and SVG for production usage use:
```
npm run release
```
…this makes sure that webpack runs production ready builds that are fully optimized.

## Adding new features / files

### SVG Sprite
We automatically generate a SVG sprite from the files located at `public/template/src/svg/`. When adding new files, please make sure they are optimized and as small as possible. One recommended tool to optimize SVGs is [SVGOMG from Jake Archibald](https://jakearchibald.github.io/svgomg/).

### CSS
Please make sure to separate CSS into different files based on components/features. This gives other developers much more orientation. When adding new files make sure to import them in the `public/template/src/scss/main.scss`.

### JS
Please make sure to separate your JS code into different files based on components/features. This gives other developers much more orientation. When adding new files make sure to use ES6 syntaxt with an `export` and import them in the `public/template/src/scss/main.js`. When you need to add polyfills make sure to them in the `polyfill` files based on browser support.
