# 23 Video for WordPress

A simple WordPress library for communicating with your [23 Video site](http://www.23video.com/) using the [23 Video API](http://www.23video.com/api/). I wrote this library since I needed it for my [23 Video extension for WordPress Media Explorer](https://github.com/soderlind/mexp-23).

## Requirements and prerequisites


This library requires WordPress and uses [wp_remote_get](http://codex.wordpress.org/Function_Reference/wp_remote_get) and [wp_remote_post](http://codex.wordpress.org/Function_Reference/wp_remote_post) to access the [23 Video API](http://www.23video.com/api/)

You are required to [manually obtain all the credentials necessary](http://www.23video.com/api/oauth#setting-up-your-application) for the 23 Video site you're communicating with.

## Usage

The library contains a single source code file, `class-wp-23-video.php`, which contains the class `WP_23_Video`. The first step to communicating with your 23 Video site is to set up an instance of this class:

```php
<?php
    require_once( 'class-wp-23-video.php' );

    $client = new WP_23_Video('http://mydomain.23video.com',
                              $consumerKey,
                              $consumerSecret,
                              $accessToken,
                              $accessTokenSecret);
```

Please note that you must supply the address to your 23 Video site _with_ the protocol specified and with _no trailing slash_.

To perform a `GET` request to an endpoint, like `/api/photo/list`, you can now simply use the client instance as follows:

```php
<?php
    $response = $client->get( '/api/photo/list' ,  array(
                    'include_unpublished_p' => 0
                  , 'video_p' => 1
                  , 'p' => 1
                ) );

```

Performing a `POST` request happens in the exact same way, although only URL encoded `POST` requests are supported by this library, which means that file uploads must be implemented manually:


```php
<?php
    $response = $client->post( '/api/photo/list' ,  array(
                    'include_unpublished_p' => 0
                  , 'video_p' => 1
                  , 'p' => 1
                ) );

```

The methods in the examples above, returns an associative array.

