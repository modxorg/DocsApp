# MODX DocsApp

The DocsApp is a slim application that serves up the [MODX documentation](https://github.com/Mark-H/Docs) from markdown format into a fully functional site.

It's in development, help is welcome.

Version-specific copies of the documentation should go into the `/doc-sources` directory. Then point a webserver at the `/public` directory to browse the documentation.


## Setting up

1. Run a [composer install](https://getcomposer.org) in the root: `composer install`
2. Copy the default settings: `cp .env-dev .env`
3. Edit `.env` in your favorite file editor to fix the paths. 
4. To run the latest version of the documentation (i.e. the version published on the modxorg/Docs repository), initialise the default documentation sources with `php docs.php sources:init`. To run a local clone of the documentation source, allowing you to immediately see your local changes inside the app, see custom sources below.
5. Point a webserver, running at least PHP 7.1, to the `/public` directory. 
6. If you use apache, `cp public/ht.access public/.htaccess` and tweak (RewriteBase) as required.

Version-specific copies of the [documentation](https://github.com/Mark-H/Docs) should go into the `/doc-sources` directory. Then point a webserver at the `/public` directory to browse the documentation.

### Custom Sources

The app uses a command line utility to help with initialising and updating documentation. The configuration for the latest (production) version of that, is in `/sources.dist.json`.

To run the app with a different set documentation source, you can create a `/sources.json` file. For example, create it like this to have a local source for `2.x` and a separate `upstream` containing the 2.x from the official repository:

```json
{
    "2.x": {
        "type": "local"
    },
    "upstream": {
        "type": "git",
        "url": "git@github.com:modxorg/Docs.git",
        "branch": "2.x"
    }
}
```

(Note that app treats "2.x" the same as "current", so to allow easy switching between versions in a local mirror, you'll want to call it something different. That's why in this example we called it "upstream")

Once you've done that, run `php docs.php sources:init` from the root of the project. (If you've run this previously, deleted the directories in the `/docs/` directory first.) You should see output like this:

```bash
$ php docs.php sources:init
Found 2 documentation sources: 2.x, upstream
Initialising "2.x" (local)
Source 2.x is of type local, so you have to initialise it manually.

Initialising "upstream" (git)
Cloning git@github.com:modxorg/Docs.git on branch 2.x into docs directory upstream...
Cloning into 'upstream'...
```

If you see an error "Doc sources definition is missing or invalid JSON", make sure that your JSON is valid. Especially commas at the end of the last entries can be troublesome.

The "upstream" version has been cloned, and you can keep that up-to-date easily now with `php docs.php sources:update`, but you still need to set up the local version of 2.x. You can add the files directly in a `/docs/2.x/` directory, or you can symlink them in from elsewhere. 

For this, you need to clone the modxorg/Docs repository, or better yet a fork of your own that you have commit access to. Where the directory is doesn't matter for the application, but let's say we want it in a `/markdown/` directory. 

Clone it like this: 

```bash
$ git clone -b 2.x git@github.com:modxorg/Docs.git markdown
Cloning into 'markdown'...
remote: Enumerating objects: 202, done.
remote: Counting objects: 100% (202/202), done.
remote: Compressing objects: 100% (104/104), done.
remote: Total 11978 (delta 110), reused 170 (delta 97), pack-reused 11776
Receiving objects: 100% (11978/11978), 4.01 MiB | 3.77 MiB/s, done.
Resolving deltas: 100% (7924/7924), done.
```

Now you need to create a symlink to dynamically pull in the version from `markdown` directory to `/docs/2.x/`. On Linux/Mac, you can use this command (from the root of the project):

```bash
ln -s ../markdown docs/2.x
```

The first path is the target, and the second path is where the link should be. Because we use relative links and we placed `markdown` in the root of the project, the target first has to go up a directory. 

### Alternative: direct from fork

You can also use a `/sources.json` configuration like this to easily pull in from your own fork:

```json
{
    "2.x": {
        "type": "git",
        "url": "git@github.com:YourUserName/Docs.git",
        "branch": "2.x"
    },
    "upstream": {
        "type": "git",
        "url": "git@github.com:modxorg/Docs.git",
        "branch": "2.x"
    }
}
```

This places your git repository in `/docs/2.x/`.

### Keeping sources up-to-date

When you've set up sources with the "git" type, `php docs.app sources:update` will automatically pull in the latest changes. **This uses a hard reset, removing any uncommitted changes, forcing the local version to take the state of the origin branch.**

When using "local" source types, you need to keep things in sync manually.

## Building assets

From the root of the project first load the dependencies with `npm install`. 

Then use `npm build:css` to build the styles or `npm run watch:css` to watch for changes to the sass files in `public/assets/scss/` and automatically build them.

## Running in a Docker Container

Run `make` and `make install` or use the provided Dockerfile/docker-compose.yml. (#3)
