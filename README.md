# Twigstem
Twigstem - Rapid prototyping with Twig templates

## Installation

For a new project, run the following to create a new composer.json file

```
composer init
```

Edit your composer to require twigstem

```
{
    ...
    "require": {
    	 "mml/twigstem": "^1.0.8", 
    }
}
```

Run composer install 

```
composer install
```

## Example Project

Create a folder called 'public' to serve your site, and another folder called "src" 
to contain all your source files (views, data etc). In the public folder, create a 
php file to handle requests:

```
<?php
  // path to application src directory (directory containing views and data)
  $appDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
  // Instantiate and run Twigstem
  $Twigstem = new \Twigstem\Server($appDir);
  $Twigstem->serve();
```

Next, we need to redirect all requests to this file. For an Apache site, we can use
an .htaccess file for this. For example

```
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^(.+) - [PT,L]
    RewriteRule ^(.*) /index.php [L]
</IfModule>
```

The final directory structure:

``` 
├── public
│   ├── .htaccess
│   ├── index.php  
├── src
│   ├── data
│   ├── views
├── vendor
├─- composer.json
```

## Overview

Twigstem will attempt to load a template matching the requested url.

```
/about => loads views/about.twig
/more/info =>  loads views/more/info.twig
```

Add new templates to the src/views directory.

## Data

Data can be associated with a page in any of these ways:
1. Add a json file with the same name as the page

```
views/products.twig
views/products.json
```


2. Add a json file with the same name as the page to the data folder.

```
views/products.twig
data/products.json
```

3. Add a comment to the page specifying the data source. Only json files in the data folder can be specified this way

```
{# data src: index.json #}
```
You can optionally include an ID when specifying a data file. If an ID is provided, the data returned is include in the page context under this ID.
For example

```
{# data src: products.json #}
{# data id:sizes src: sizelist.json #}
```

## Extending Twig

Twigstem will look for a class called TwigExtension. If found, this will 
be instantiated and added to twig as an extension. 
