# git-checker

 Web view for local git repository status.

## Installation

It's recommended that you use [Composer](https://getcomposer.org/) to install the git-checker.

```bash
$ composer create-project ichhabrecht/git-checker --keep-vcs --no-dev
```

This will clone and install the git-checker and all required dependencies.

## Usage

Point your document root to "public" sub folder in the "git-checker" directory (or the one you specified for the installation).

If you need to change some configuration options, please have a look at app/settings.yml.

## Update 1.x -> 2.x

Please note that the structure of the settings.yml file has changed from 1.x to 2.x.

**settings.yml**

You need to change the key `virtual-hosts:` to `virtual-host:`.
