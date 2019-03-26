# MODX DocsApp

The DocsApp is a slim application that serves up the [MODX documentation](https://github.com/Mark-H/Docs) from markdown format into a fully functional site.

It's in development, help is welcome.

Version-specific copies of the documentation should go into the `/doc-sources` directory. Then point a webserver at the `/public` directory to browse the documentation.


## Setting up

1. Clone the repository with submodules: `git clone --recurse-submodules https://github.com/Mark-H/DocsApp`
2. Run a [composer install](https://getcomposer.org) in the root: `composer install`
3. Copy the default settings: `cp .env-dev .env`
4. Edit `.env` in your favorite file editor to fix the paths. 
5. Point a webserver, running at least PHP 7.1, to the `/public` directory. 
6. If you use apache, `cp public/ht.access public/.htaccess` and tweak (RewriteBase) as required.

Version-specific copies of the [documentation](https://github.com/Mark-H/Docs) should go into the `/doc-sources` directory. Then point a webserver at the `/public` directory to browse the documentation.

## Building assets

From the root of the project first load the dependencies with `npm install`. 

Then use `npm build:css` to build the styles or `npm run watch:css` to watch for changes to the sass files in `public/assets/scss/` and automatically build them.

## Running in a Docker Container

Run `make` and `make install` or use the provided Dockerfile/docker-compose.yml. (#3)
