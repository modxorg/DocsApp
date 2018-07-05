# MODX DocsApp

The DocsApp is a slim application that serves up the [MODX documentation](https://github.com/Mark-H/Docs) from markdown format into a fully functional site.

It's in development, help is welcome.

Version-specific copies of the documentation should go into the `/doc-sources` directory. Then point a webserver at the `/public` directory to browse the documentation.


## Setting up

1. Clone the repository with submodules: `git clone --recurse-submodules https://github.com/Mark-H/DocsApp`
2. Run a [composer install](https://getcomposer.org) in the root: `composer install`
3. Create environment.php: `cp environment.sample.php environment.php`
4. Edit environment.php in your favorite file editor. Set the appropriate hostname and directory. 
5. Point a webserver, running at least PHP 7.1, to the `/public` directory. 
6. If you use apache, `cp public/ht.access public/.htaccess` and tweak (RewriteBase) as required.

## Building assets

From the root of the project first load the dependencies with `npm install`. 

Then use `npm build:css` to build the styles or `npm run watch:css` to watch for changes to the sass files in `public/assets/scss/` and automatically build them.

