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
    	 "mml/twigstem": "^1.0.2", 
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